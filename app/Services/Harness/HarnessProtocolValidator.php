<?php

namespace App\Services\Harness;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use RuntimeException;

class HarnessProtocolValidator
{
    public const EVENT_SCHEMA = 'kaiwu.event/v1';

    public const QUEST_SCHEMA = 'kaiwu.quest/v1';

    /** @var array<string, object> */
    private array $schemas = [];

    public function __construct(private readonly Validator $validator) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function validate(array $payload, ?string $expectedSchema = null): void
    {
        $schemaName = $expectedSchema ?? ($payload['schema'] ?? null);

        if (! is_string($schemaName) || ! array_key_exists($schemaName, $this->schemaPaths())) {
            throw new RuntimeException('未知或缺失的 KAIWU schema。');
        }

        $document = json_decode(
            json_encode($payload, JSON_THROW_ON_ERROR),
            false,
            512,
            JSON_THROW_ON_ERROR,
        );
        $result = $this->validator->validate($document, $this->schema($schemaName));

        if ($result->isValid()) {
            return;
        }

        $formatted = (new ErrorFormatter)->formatOutput($result->error(), 'basic');
        $messages = array_map(
            static fn (array $error): string => ($error['instanceLocation'] ?: '#').' '.$error['error'],
            $formatted['errors'] ?? [],
        );

        throw new RuntimeException(implode('; ', $messages) ?: '协议校验失败。');
    }

    private function schema(string $name): object
    {
        if (isset($this->schemas[$name])) {
            return $this->schemas[$name];
        }

        $path = $this->schemaPaths()[$name];
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("无法读取协议 Schema：{$path}");
        }

        return $this->schemas[$name] = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    private function schemaPaths(): array
    {
        return [
            self::EVENT_SCHEMA => base_path('schemas/kaiwu-event-v1.schema.json'),
            self::QUEST_SCHEMA => base_path('schemas/kaiwu-quest-v1.schema.json'),
        ];
    }
}
