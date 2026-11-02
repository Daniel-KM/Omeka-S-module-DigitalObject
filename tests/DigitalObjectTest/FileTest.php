<?php declare(strict_types=1);

namespace DigitalObjectTest;

use CommonTest\AbstractTestCase;
use Omeka\Api\Exception\ValidationException;
use Omeka\File\TempFile;

/**
 * The file of a digital object: the internal temp file of a process, the
 * validation of the allowed files and the removal of the files with the
 * resource.
 */
class FileTest extends AbstractTestCase
{
    protected array $createdIds = [];

    protected array $tempFiles = [];

    public function setUp(): void
    {
        parent::setUp();
        $auth = $this->getService('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    public function tearDown(): void
    {
        foreach ($this->createdIds as $id) {
            try {
                $this->api()->delete('digital_objects', $id);
            } catch (\Throwable $e) {
            }
        }
        foreach ($this->tempFiles as $tempFile) {
            @unlink($tempFile->getTempPath());
        }
        parent::tearDown();
    }

    public function testTempFileIsStoredAndKeptForTheCaller(): void
    {
        $tempFile = $this->tempFile('Some text.');
        $do = $this->api()->create('digital_objects', [
            'ingest_tempfile' => $tempFile,
            'o:source' => 'notes.txt',
        ])->getContent();
        $this->createdIds[] = $do->id();

        $this->assertTrue($do->hasOriginal());
        $this->assertSame('txt', $do->extension());
        $this->assertSame('text/plain', $do->mediaType());
        $this->assertSame('notes.txt', $do->source());
        $this->assertFileExists($this->originalPath($do->filename()));
        $this->assertSame('Some text.', file_get_contents($this->originalPath($do->filename())));
        // The temp file belongs to the caller, that removes it.
        $this->assertFileExists($tempFile->getTempPath());
    }

    public function testTempFileMustBeAnObject(): void
    {
        $this->expectException(ValidationException::class);
        $this->api()->create('digital_objects', [
            'ingest_tempfile' => '/etc/passwd',
            'o:source' => 'passwd.txt',
        ]);
    }

    public function testDisallowedFileIsRefused(): void
    {
        $settings = $this->getService('Omeka\Settings');
        $previous = $settings->get('disable_file_validation');
        $settings->set('disable_file_validation', false);
        $tempFile = $this->tempFile('<?php echo "x";');
        try {
            $this->api()->create('digital_objects', [
                'ingest_tempfile' => $tempFile,
                'o:source' => 'script.php',
            ]);
            $this->fail('A disallowed file should be refused.');
        } catch (ValidationException $e) {
            // Refused by the validator, not because the file is missing.
            $messages = json_encode($e->getErrorStore()->getErrors());
            $this->assertStringNotContainsString('is required', $messages);
            $this->assertMatchesRegularExpression('~media type|extension~i', $messages);
        } finally {
            $settings->set('disable_file_validation', $previous);
        }
        $this->assertFileExists($tempFile->getTempPath());
    }

    public function testSideloadRequiresModuleFileSideload(): void
    {
        if (class_exists(\FileSideload\Module::class, false)) {
            $this->markTestSkipped('The module FileSideload is installed.');
        }
        $this->expectException(ValidationException::class);
        $this->api()->create('digital_objects', [
            'ingest_filename' => '/etc/hostname',
        ]);
    }

    public function testFileIsRequiredOnCreate(): void
    {
        $this->expectException(ValidationException::class);
        $this->api()->create('digital_objects', []);
    }

    public function testFilesAreRemovedWithTheResource(): void
    {
        $tempFile = $this->tempFile('Some text.');
        $do = $this->api()->create('digital_objects', [
            'ingest_tempfile' => $tempFile,
            'o:source' => 'notes.txt',
        ])->getContent();
        $filepath = $this->originalPath($do->filename());
        $this->assertFileExists($filepath);

        $this->api()->delete('digital_objects', $do->id());
        $this->assertFileDoesNotExist($filepath);
    }

    protected function tempFile(string $content): TempFile
    {
        $tempFile = $this->getService('Omeka\File\TempFileFactory')->build();
        file_put_contents($tempFile->getTempPath(), $content);
        $this->tempFiles[] = $tempFile;
        return $tempFile;
    }

    protected function originalPath(string $filename): string
    {
        $basePath = $this->getService('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        return $basePath . '/original/' . $filename;
    }

    protected function api(): \Omeka\Api\Manager
    {
        return $this->getService('Omeka\ApiManager');
    }
}
