<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mmt\Jwf\Forms\Domain\FormDocument;
use Mmt\Jwf\Forms\Domain\Input\InputType;
use Mmt\Jwf\Forms\Domain\Node\ContainerNode;
use Mmt\Jwf\Forms\Domain\Node\FormNode;
use Mmt\Jwf\Forms\Domain\Node\InputNode;
use Mmt\Jwf\Shared\Domain\NodeId;
use Mmt\Jwf\ValidationProfiles\Domain\RuleDefinition;
use Mmt\JwfLaravel\Exceptions\FileRejectedException;
use Mmt\JwfLaravel\Tests\TestCase;

final class FileArtifactTest extends TestCase
{
    public function testDeletingASubmissionLeavesTheFileForExplicitManualDeletion(): void
    {
        Storage::fake('jwf-tests');
        $jwf = $this->jwf();
        $input = new InputNode(NodeId::generate(), 0, InputType::File, 'document');
        $form = new FormNode(NodeId::generate(), 0, 'upload', [$input]);
        $draft = new FormDocument(
            NodeId::generate(),
            new ContainerNode(NodeId::generate(), 0, [$form]),
        );
        $published = $jwf->forms()->publish($jwf->forms()->create('Upload', $draft)->document->id->toString());

        $result = $jwf->submit(
            $published->document->id->toString(),
            $form->id->toString(),
            ['document' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf')],
        );
        self::assertNotNull($result->submission);
        self::assertCount(1, Storage::disk('jwf-tests')->allFiles('jwf'));

        $jwf->deleteSubmission($result->submission->id->toString());
        $orphans = $jwf->artifacts()->unreferenced();
        self::assertCount(1, $orphans);
        self::assertCount(1, Storage::disk('jwf-tests')->allFiles('jwf'));

        $jwf->artifacts()->delete($orphans[0]->id);
        self::assertCount(0, Storage::disk('jwf-tests')->allFiles('jwf'));
        self::assertDatabaseCount('jwf_file_artifacts', 0);
    }

    public function testInvalidSubmissionCompensatesItsStagedFile(): void
    {
        Storage::fake('jwf-tests');
        $jwf = $this->jwf();
        $profile = $jwf->profiles()->create('required-text');
        $profileVersion = $jwf->profiles()->createVersion(
            $profile->id->toString(),
            [InputType::Text],
            [new RuleDefinition('required')],
        );
        $input = new InputNode(NodeId::generate(), 0, InputType::File, 'document');
        $required = new InputNode(
            NodeId::generate(),
            1,
            InputType::Text,
            'required',
            validationProfileVersions: [$profileVersion->reference()],
        );
        $form = new FormNode(NodeId::generate(), 0, 'upload', [$input, $required]);
        $draft = new FormDocument(NodeId::generate(), new ContainerNode(NodeId::generate(), 0, [$form]));
        $published = $jwf->forms()->publish($jwf->forms()->create('Upload', $draft)->document->id->toString());

        $result = $jwf->submit(
            $published->document->id->toString(),
            $form->id->toString(),
            ['document' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf')],
        );

        self::assertFalse($result->isValid());
        self::assertCount(0, Storage::disk('jwf-tests')->allFiles('jwf'));
        self::assertDatabaseCount('jwf_file_artifacts', 0);
    }

    public function testInputConfigurationCanRestrictUploadedFiles(): void
    {
        Storage::fake('jwf-tests');
        $jwf = $this->jwf();
        $input = new InputNode(
            NodeId::generate(),
            0,
            InputType::File,
            'document',
            configuration: ['maxSizeKb' => 1, 'allowedExtensions' => ['pdf']],
        );
        $form = new FormNode(NodeId::generate(), 0, 'upload', [$input]);
        $draft = new FormDocument(NodeId::generate(), new ContainerNode(NodeId::generate(), 0, [$form]));
        $published = $jwf->forms()->publish($jwf->forms()->create('Restricted', $draft)->document->id->toString());

        $this->expectException(FileRejectedException::class);

        try {
            $jwf->submit(
                $published->document->id->toString(),
                $form->id->toString(),
                ['document' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf')],
            );
        } finally {
            self::assertDatabaseCount('jwf_file_artifacts', 0);
            self::assertCount(0, Storage::disk('jwf-tests')->allFiles('jwf'));
        }
    }
}
