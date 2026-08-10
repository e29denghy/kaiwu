<?php

namespace App\Console\Commands;

use App\Services\Harness\HarnessProtocolValidator;
use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use Throwable;

class ValidateHarnessProtocol extends Command
{
    protected $signature = 'harness:validate
        {path : Path to a JSON or JSONL protocol document}
        {--schema=auto : auto, event, or quest}';

    protected $description = 'Validate KAIWU event or Quest protocol documents without importing or executing them';

    public function handle(HarnessProtocolValidator $validator): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("File is not readable: {$path}");

            return self::FAILURE;
        }

        try {
            $expectedSchema = $this->expectedSchema();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $documents = 0;
        $errors = [];

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'jsonl') {
            $lines = file($path, FILE_IGNORE_NEW_LINES);

            if ($lines === false) {
                $this->error("Unable to read file: {$path}");

                return self::FAILURE;
            }

            foreach ($lines as $index => $line) {
                if (trim($line) === '') {
                    continue;
                }

                try {
                    $validator->validate($this->decode($line), $expectedSchema);
                    $documents++;
                } catch (Throwable $exception) {
                    $errors[] = 'line '.($index + 1).': '.$exception->getMessage();
                }
            }
        } else {
            try {
                $contents = file_get_contents($path);

                if ($contents === false) {
                    throw new RuntimeException('Unable to read file.');
                }

                $validator->validate($this->decode($contents), $expectedSchema);
                $documents++;
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        foreach ($errors as $error) {
            $this->error($error);
        }

        if ($documents === 0 && $errors === []) {
            $this->error('Invalid: no protocol documents found.');

            return self::FAILURE;
        }

        if ($errors !== []) {
            $this->newLine();
            $this->error('Invalid: '.count($errors).' document(s) failed protocol validation.');

            return self::FAILURE;
        }

        $this->info("Valid: {$documents} document(s).");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function decode(string $json): array
    {
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload) || array_is_list($payload)) {
            throw new RuntimeException('Protocol document must be a JSON object.');
        }

        return $payload;
    }

    private function expectedSchema(): ?string
    {
        return match ($this->option('schema')) {
            'auto' => null,
            'event' => HarnessProtocolValidator::EVENT_SCHEMA,
            'quest' => HarnessProtocolValidator::QUEST_SCHEMA,
            default => throw new RuntimeException('The --schema option must be auto, event, or quest.'),
        };
    }
}
