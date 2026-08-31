<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Forms;

use Mmt\Jwf\Forms\Domain\FormDocument;
use Mmt\Jwf\Forms\Domain\Node\ContainerNode;
use Mmt\Jwf\Forms\Domain\Node\FormNode;
use Mmt\Jwf\Forms\Domain\Node\InputNode;
use Mmt\Jwf\Forms\Domain\Node\Node;
use Mmt\Jwf\ValidationProfiles\Domain\ValidationProfileVersionReference;
use Mmt\JwfLaravel\Support\DefaultValidationProfiles;
use Mmt\JwfLaravel\ValidationProfiles\Repositories\ValidationProfileRepository;

final readonly class DefaultInputProfiles
{
    public function __construct(
        private DefaultValidationProfiles $definitions,
        private ValidationProfileRepository $profiles,
    ) {
    }

    public function apply(FormDocument $document): FormDocument
    {
        $references = $this->emptyReferences();

        return new FormDocument(
            $document->id,
            $this->container($document->root, $references),
            $document->state,
        );
    }

    /** @param array<string, ValidationProfileVersionReference> $references */
    private function container(
        ContainerNode $container,
        array &$references,
    ): ContainerNode {
        return new ContainerNode(
            $container->id,
            $container->position,
            array_map(
                fn (Node $node): Node => $this->node($node, $references),
                $container->children,
            ),
            $container->attributes,
        );
    }

    /** @param array<string, ValidationProfileVersionReference> $references */
    private function node(Node $node, array &$references): Node
    {
        if ($node instanceof ContainerNode) {
            return $this->container($node, $references);
        }
        if ($node instanceof FormNode) {
            return new FormNode(
                $node->id,
                $node->position,
                $node->name,
                array_map(
                    fn (Node $child): Node => $this->node($child, $references),
                    $node->children,
                ),
                $node->attributes,
            );
        }
        if ($node instanceof InputNode) {
            return $this->input($node, $references);
        }

        return $node;
    }

    /** @param array<string, ValidationProfileVersionReference> $references */
    private function input(InputNode $input, array &$references): InputNode
    {
        $profiles = $input->validationProfileVersions;
        foreach ($this->definitions->forInput($input->type) as $definition) {
            $references[$definition->name] ??= $this->profiles->ensureDefault($definition);
            if (!$this->contains($profiles, $references[$definition->name])) {
                $profiles[] = $references[$definition->name];
            }
        }

        return new InputNode(
            $input->id,
            $input->position,
            $input->type,
            $input->name,
            $input->label,
            $input->description,
            $input->options,
            $input->configuration,
            $input->attributes,
            $profiles,
        );
    }

    /** @param list<ValidationProfileVersionReference> $profiles */
    private function contains(array $profiles, ValidationProfileVersionReference $profile): bool
    {
        foreach ($profiles as $candidate) {
            if ($candidate->versionId->toString() === $profile->versionId->toString()) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, ValidationProfileVersionReference> */
    private function emptyReferences(): array
    {
        return [];
    }
}
