<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Forms;

use Mmt\Jwf\Forms\Domain\FormDocument;
use Mmt\Jwf\Forms\Domain\Input\InputType;
use Mmt\Jwf\Forms\Domain\Node\ContainerNode;
use Mmt\Jwf\Forms\Domain\Node\FormNode;
use Mmt\Jwf\Forms\Domain\Node\InputNode;
use Mmt\Jwf\ValidationProfiles\Domain\ValidationProfileVersionReference;
use Mmt\JwfLaravel\Authorization\AuthorizationContext;
use Mmt\JwfLaravel\Authorization\JwfOperation;
use Mmt\JwfLaravel\Contracts\JwfAuthorizer;
use Mmt\JwfLaravel\Exceptions\JwfLaravelException;
use Mmt\JwfLaravel\Forms\Data\StoredFormVersion;
use Mmt\JwfLaravel\Forms\Repositories\FormRepository;
use Mmt\JwfLaravel\ValidationProfiles\Repositories\ValidationProfileRepository;
use Mmt\JwfLaravel\ValidationProfiles\RuleCompiler;

final readonly class FormManager
{
    public function __construct(
        private FormRepository $forms,
        private DefaultInputProfiles $defaultProfiles,
        private ValidationProfileRepository $profiles,
        private RuleCompiler $rules,
        private JwfAuthorizer $authorizer,
    ) {
    }

    public function create(
        string $name,
        FormDocument $draft,
        AuthorizationContext $context = new AuthorizationContext(),
    ): StoredFormVersion {
        $this->authorizer->authorize(JwfOperation::ManageForms, $context);

        return $this->forms->create($name, $this->defaultProfiles->apply($draft));
    }

    public function saveDraft(
        FormDocument $draft,
        AuthorizationContext $context = new AuthorizationContext(),
    ): StoredFormVersion {
        $this->authorizer->authorize(JwfOperation::ManageForms, $context);

        return $this->forms->saveDraft($this->defaultProfiles->apply($draft));
    }

    public function get(
        string $versionId,
        AuthorizationContext $context = new AuthorizationContext(),
    ): StoredFormVersion {
        $this->authorizer->authorize(JwfOperation::ReadForms, $context);

        return $this->forms->find($versionId);
    }

    public function cloneVersion(
        string $sourceVersionId,
        AuthorizationContext $context = new AuthorizationContext(),
    ): StoredFormVersion {
        $this->authorizer->authorize(JwfOperation::ManageForms, $context);

        return $this->forms->cloneVersion($sourceVersionId);
    }

    public function publish(
        string $versionId,
        AuthorizationContext $context = new AuthorizationContext(),
    ): StoredFormVersion {
        $this->authorizer->authorize(JwfOperation::PublishForms, $context);
        $stored = $this->forms->find($versionId);
        $this->assertUniqueTransportNames($stored->document->root);
        $this->validateProfileReferences($stored->document->root);

        return $this->forms->publish($versionId);
    }

    public function archive(
        string $versionId,
        AuthorizationContext $context = new AuthorizationContext(),
    ): StoredFormVersion {
        $this->authorizer->authorize(JwfOperation::PublishForms, $context);

        return $this->forms->archive($versionId);
    }

    public function deleteVersion(
        string $versionId,
        AuthorizationContext $context = new AuthorizationContext(),
    ): void {
        $this->authorizer->authorize(JwfOperation::ManageForms, $context);
        $this->forms->deleteVersion($versionId);
    }

    public function deleteTemplate(
        string $templateId,
        AuthorizationContext $context = new AuthorizationContext(),
    ): void {
        $this->authorizer->authorize(JwfOperation::ManageForms, $context);
        $this->forms->deleteTemplate($templateId);
    }

    private function assertUniqueTransportNames(ContainerNode $root): void
    {
        foreach ($this->formsIn($root) as $form) {
            $names = array_map(static fn (InputNode $input): string => $input->name, $this->inputsIn($form));
            if (count($names) !== count(array_unique($names))) {
                throw new JwfLaravelException('Input transport names must be unique within each form.');
            }
        }
    }

    private function validateProfileReferences(ContainerNode $root): void
    {
        foreach ($this->formsIn($root) as $form) {
            foreach ($this->inputsIn($form) as $input) {
                foreach ($input->validationProfileVersions as $reference) {
                    $resolved = $this->profiles->resolve($reference);
                    $expected = $this->typeNames($reference);
                    $actual = array_map(static fn (InputType $type): string => $type->value, $resolved->compatibleTypes);
                    sort($actual);
                    if ($resolved->profileId->toString() !== $reference->profileId->toString()
                        || $resolved->number !== $reference->version
                        || $actual !== $expected
                        || !$resolved->supports($input->type)
                    ) {
                        throw new JwfLaravelException('A validation profile reference does not match its stored version.');
                    }
                    $this->rules->compile($resolved->rules);
                }
            }
        }
    }

    /** @return list<FormNode> */
    private function formsIn(ContainerNode $container): array
    {
        $forms = [];
        foreach ($container->children as $child) {
            if ($child instanceof FormNode) {
                $forms[] = $child;
            } elseif ($child instanceof ContainerNode) {
                array_push($forms, ...$this->formsIn($child));
            }
        }

        return $forms;
    }

    /** @return list<InputNode> */
    private function inputsIn(FormNode|ContainerNode $parent): array
    {
        $inputs = [];
        foreach ($parent->children as $child) {
            if ($child instanceof InputNode) {
                $inputs[] = $child;
            } elseif ($child instanceof ContainerNode) {
                array_push($inputs, ...$this->inputsIn($child));
            }
        }

        return $inputs;
    }

    /** @return list<string> */
    private function typeNames(ValidationProfileVersionReference $reference): array
    {
        $types = array_map(static fn (InputType $type): string => $type->value, $reference->compatibleTypes);
        sort($types);

        return $types;
    }
}
