<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Authorization;

enum JwfOperation: string
{
    case ManageForms = 'manage_forms';
    case PublishForms = 'publish_forms';
    case ReadForms = 'read_forms';
    case ManageProfiles = 'manage_profiles';
    case Submit = 'submit';
    case DeleteSubmission = 'delete_submission';
    case ReadArtifact = 'read_artifact';
    case DeleteArtifact = 'delete_artifact';
}
