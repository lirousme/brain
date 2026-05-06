<?php

declare(strict_types=1);

use Laudis\Neo4j\ClientBuilder;

const DEFAULT_NEO4J_BOLT_URL = 'bolt://neo4j:75351595@localhost:7687';

/**
 * Reads the configured Neo4j Bolt DSN.
 */
function neo4j_bolt_url(): string
{
    return getenv('NEO4J_BOLT_URL') ?: DEFAULT_NEO4J_BOLT_URL;
}

/**
 * Creates a Laudis Neo4j client using the Bolt driver.
 */
function neo4j_client(): object
{
    return ClientBuilder::create()
        ->withDriver('bolt', neo4j_bolt_url())
        ->withDefaultDriver('bolt')
        ->build();
}

/**
 * Extracts scalar values from a Neo4j query result.
 *
 * @return list<string>
 */
function neo4j_fetch_column(object $client, string $cypher, string $column): array
{
    $result = $client->run($cypher);
    $records = method_exists($result, 'getResult') ? $result->getResult() : $result;
    $values = [];

    foreach ($records as $record) {
        $value = $record->get($column);

        if ($value !== null && $value !== '') {
            $values[] = (string) $value;
        }
    }

    $values = array_values(array_unique($values));
    natcasesort($values);

    return array_values($values);
}

/**
 * Loads the labels, relationship types and property keys currently present in Neo4j.
 *
 * @return array{nodes: list<string>, relationships: list<string>, properties: list<string>, error: ?string}
 */
function neo4j_schema_overview(): array
{
    if (!class_exists(ClientBuilder::class)) {
        return [
            'nodes' => [],
            'relationships' => [],
            'properties' => [],
            'error' => 'Dependências ausentes. Execute `composer install` para instalar laudis/neo4j-php-client.',
        ];
    }

    try {
        $client = neo4j_client();

        return [
            'nodes' => neo4j_fetch_column(
                $client,
                'CALL db.labels() YIELD label RETURN label ORDER BY label',
                'label'
            ),
            'relationships' => neo4j_fetch_column(
                $client,
                'CALL db.relationshipTypes() YIELD relationshipType RETURN relationshipType ORDER BY relationshipType',
                'relationshipType'
            ),
            'properties' => neo4j_fetch_column(
                $client,
                'CALL db.propertyKeys() YIELD propertyKey RETURN propertyKey ORDER BY propertyKey',
                'propertyKey'
            ),
            'error' => null,
        ];
    } catch (Throwable $exception) {
        return [
            'nodes' => [],
            'relationships' => [],
            'properties' => [],
            'error' => 'Não foi possível consultar o Neo4j via Bolt: ' . $exception->getMessage(),
        ];
    }
}

/**
 * Escapes values for safe HTML output.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
