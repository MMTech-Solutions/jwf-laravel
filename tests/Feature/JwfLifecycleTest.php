<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Mmt\Jwf\Forms\Domain\FormDocument;
use Mmt\Jwf\Forms\Domain\Input\InputType;
use Mmt\Jwf\Forms\Domain\Node\ContainerNode;
use Mmt\Jwf\Forms\Domain\Node\FormNode;
use Mmt\Jwf\Forms\Domain\Node\InputNode;
use Mmt\Jwf\Shared\Domain\NodeId;
use Mmt\Jwf\ValidationProfiles\Domain\RuleDefinition;
use Mmt\JwfLaravel\Exceptions\FormHasSubmissionsException;
use Mmt\JwfLaravel\Tests\TestCase;

final class JwfLifecycleTest extends TestCase
{
    public function testItClonesPublishesValidatesSubmitsAndProtectsDefinitions(): void
    {
        $jwf = $this->jwf();
        $profile = $jwf->profiles()->create('required-text');
        $profileVersion = $jwf->profiles()->createVersion(
            $profile->id->toString(),
            [InputType::Text],
            [new RuleDefinition('required'), new RuleDefinition('string')],
        );
        [$draft, $form, $input] = $this->document(InputType::Text, [$profileVersion->reference()]);

        $stored = $jwf->forms()->create('Contact', $draft);
        $copy = $jwf->forms()->cloneVersion($stored->document->id->toString());

        self::assertSame(2, $copy->number);
        self::assertSame($stored->document->root->toArray(), $copy->document->root->toArray());
        self::assertNotSame($stored->document->id->toString(), $copy->document->id->toString());

        $published = $jwf->forms()->publish($stored->document->id->toString());
        self::assertFalse($jwf->validate($published->document->id->toString(), $form->id->toString(), [])->isValid());

        $result = $jwf->submit(
            $published->document->id->toString(),
            $form->id->toString(),
            [$input->name => 'Ada'],
        );
        self::assertTrue($result->isValid());
        self::assertNotNull($result->submission);
        self::assertDatabaseCount('jwf_submissions', 1);
        self::assertDatabaseCount('jwf_submission_values', 1);

        try {
            $jwf->forms()->deleteVersion($published->document->id->toString());
            self::fail('A version with submissions was deleted.');
        } catch (FormHasSubmissionsException) {
            self::assertDatabaseCount('jwf_form_versions', 2);
        }

        $jwf->deleteSubmission($result->submission->id->toString());
        $jwf->forms()->deleteVersion($published->document->id->toString());
        self::assertDatabaseCount('jwf_form_versions', 1);
    }

    public function testItEncryptsPasswordValuesAtRest(): void
    {
        $jwf = $this->jwf();
        [$draft, $form, $input] = $this->document(InputType::Password);
        $published = $jwf->forms()->publish($jwf->forms()->create('Secret', $draft)->document->id->toString());

        $jwf->submit(
            $published->document->id->toString(),
            $form->id->toString(),
            [$input->name => 'top-secret'],
        );

        $row = DB::table('jwf_submission_values')->first();
        self::assertNotNull($row);
        self::assertTrue((bool) $row->sensitive);
        self::assertIsString($row->value);
        self::assertStringNotContainsString('top-secret', $row->value);
    }

    public function testItCanFaithfullyCloneEveryVersionState(): void
    {
        $jwf = $this->jwf();
        [$draft] = $this->document(InputType::Text);
        $source = $jwf->forms()->create('Versions', $draft);

        $draftCopy = $jwf->forms()->cloneVersion($source->document->id->toString());
        $published = $jwf->forms()->publish($source->document->id->toString());
        $publishedCopy = $jwf->forms()->cloneVersion($published->document->id->toString());
        $archived = $jwf->forms()->archive($published->document->id->toString());
        $archivedCopy = $jwf->forms()->cloneVersion($archived->document->id->toString());

        foreach ([$draftCopy, $publishedCopy, $archivedCopy] as $copy) {
            self::assertSame($source->document->root->toArray(), $copy->document->root->toArray());
            self::assertSame('draft', $copy->document->state->value);
            self::assertNotSame($source->document->id->toString(), $copy->document->id->toString());
        }
        self::assertSame([2, 3, 4], [$draftCopy->number, $publishedCopy->number, $archivedCopy->number]);
    }

    /** @param list<\Mmt\Jwf\ValidationProfiles\Domain\ValidationProfileVersionReference> $profiles
     * @return array{FormDocument, FormNode, InputNode}
     */
    private function document(InputType $type, array $profiles = []): array
    {
        $input = new InputNode(NodeId::generate(), 0, $type, 'value', validationProfileVersions: $profiles);
        $form = new FormNode(NodeId::generate(), 0, 'main', [$input]);
        $document = new FormDocument(
            NodeId::generate(),
            new ContainerNode(NodeId::generate(), 0, [$form]),
        );

        return [$document, $form, $input];
    }
}
