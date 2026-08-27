<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\ValidationProfiles;

use Mmt\Jwf\Forms\Domain\Input\InputType;
use Mmt\Jwf\Shared\Domain\NodeId;
use Mmt\Jwf\ValidationProfiles\Domain\RuleDefinition;
use Mmt\Jwf\ValidationProfiles\Domain\ValidationProfile;
use Mmt\Jwf\ValidationProfiles\Domain\ValidationProfileVersion;
use Mmt\JwfLaravel\Authorization\AuthorizationContext;
use Mmt\JwfLaravel\Authorization\JwfOperation;
use Mmt\JwfLaravel\Contracts\JwfAuthorizer;
use Mmt\JwfLaravel\ValidationProfiles\Repositories\ValidationProfileRepository;

final readonly class ValidationProfileManager
{
    public function __construct(
        private ValidationProfileRepository $profiles,
        private RuleCompiler $compiler,
        private JwfAuthorizer $authorizer,
    ) {
    }

    public function create(
        string $name,
        AuthorizationContext $context = new AuthorizationContext(),
    ): ValidationProfile {
        $this->authorizer->authorize(JwfOperation::ManageProfiles, $context);

        return $this->profiles->create(new ValidationProfile(NodeId::generate(), $name));
    }

    public function setActive(
        string $profileId,
        bool $active,
        AuthorizationContext $context = new AuthorizationContext(),
    ): void {
        $this->authorizer->authorize(JwfOperation::ManageProfiles, $context);
        $this->profiles->setActive($profileId, $active);
    }

    /** @param list<InputType> $types
     * @param list<RuleDefinition> $rules
     */
    public function createVersion(
        string $profileId,
        array $types,
        array $rules,
        AuthorizationContext $context = new AuthorizationContext(),
    ): ValidationProfileVersion {
        $this->authorizer->authorize(JwfOperation::ManageProfiles, $context);
        $this->compiler->compile($rules);

        return $this->profiles->createVersion($profileId, $types, $rules);
    }
}
