<?php

declare(strict_types=1);

use Laudis\Neo4j\ClientBuilder;

const DEFAULT_NEO4J_BOLT_URL = 'bolt://neo4j:75351595@localhost:7687';
const NEO4J_SCHEMA_REGISTRY_LABEL = '__BrainSchemaRegistry';
const NEO4J_SCHEMA_REGISTRY_RELATIONSHIP = '__BRAIN_SCHEMA_REGISTRY_RELATIONSHIP';
const NEO4J_SCHEMA_REGISTRY_ID_PROPERTY = '__brain_schema_registry_id';
const NEO4J_SCHEMA_REGISTRY_VALUE_PROPERTY = '__brain_schema_registry_value';

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
 * Escapes a Neo4j schema identifier for use inside backticks.
 */
function neo4j_escape_schema_identifier(string $identifier): string
{
    return str_replace('`', '``', $identifier);
}

/**
 * Validates an incoming label, relationship type or property key name.
 */
function neo4j_clean_schema_name(?string $name): string
{
    $name = trim((string) $name);

    if ($name === '') {
        throw new InvalidArgumentException('Informe um nome antes de criar ou alterar o item no banco.');
    }

    if (str_contains($name, "\0")) {
        throw new InvalidArgumentException('O nome não pode conter caractere nulo.');
    }

    if (strlen($name) > 128) {
        throw new InvalidArgumentException('Use um nome com até 128 caracteres.');
    }

    return $name;
}

/**
 * Extracts scalar values from a Neo4j query result.
 *
 * @return list<string>
 */
function neo4j_fetch_column(object $client, string $cypher, string $column, array $parameters = []): array
{
    $result = $client->run($cypher, $parameters);
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
 * Creates a schema token for a node label, relationship type or property key in Neo4j.
 */
function neo4j_create_schema_item(string $type, string $name): void
{
    if (!class_exists(ClientBuilder::class)) {
        throw new RuntimeException('Dependências ausentes. Execute `composer install` para instalar laudis/neo4j-php-client.');
    }

    $name = neo4j_clean_schema_name($name);
    $escapedName = neo4j_escape_schema_identifier($name);
    $registryLabel = neo4j_escape_schema_identifier(NEO4J_SCHEMA_REGISTRY_LABEL);
    $registryRelationship = neo4j_escape_schema_identifier(NEO4J_SCHEMA_REGISTRY_RELATIONSHIP);
    $registryIdProperty = neo4j_escape_schema_identifier(NEO4J_SCHEMA_REGISTRY_ID_PROPERTY);
    $registryValueProperty = neo4j_escape_schema_identifier(NEO4J_SCHEMA_REGISTRY_VALUE_PROPERTY);
    $client = neo4j_client();

    match ($type) {
        'nodes' => $client->run(
            'MERGE (registry:`' . $registryLabel . '` {`' . $registryIdProperty . '`: $registryId}) '
            . 'SET registry:`' . $escapedName . '`, registry.`' . $registryValueProperty . '` = $name',
            ['registryId' => 'label:' . $name, 'name' => $name]
        ),
        'relationships' => $client->run(
            'MERGE (start:`' . $registryLabel . '` {`' . $registryIdProperty . '`: $startId}) '
            . 'MERGE (end:`' . $registryLabel . '` {`' . $registryIdProperty . '`: $endId}) '
            . 'MERGE (start)-[relationship:`' . $escapedName . '`]->(end) '
            . 'SET relationship.`' . $registryValueProperty . '` = $name',
            ['startId' => 'relationship:' . $name . ':start', 'endId' => 'relationship:' . $name . ':end', 'name' => $name]
        ),
        'properties' => $client->run(
            'MERGE (registry:`' . $registryLabel . '` {`' . $registryIdProperty . '`: $registryId}) '
            . 'SET registry.`' . $escapedName . '` = $name',
            ['registryId' => 'property:' . $name, 'name' => $name]
        ),
        default => throw new InvalidArgumentException('Tipo de estrutura inválido.'),
    };
}

/**
 * Renames an existing node label, relationship type or property key in Neo4j.
 */
function neo4j_rename_schema_item(string $type, string $currentName, string $newName): void
{
    if (!class_exists(ClientBuilder::class)) {
        throw new RuntimeException('Dependências ausentes. Execute `composer install` para instalar laudis/neo4j-php-client.');
    }

    $currentName = neo4j_clean_schema_name($currentName);
    $newName = neo4j_clean_schema_name($newName);

    if ($currentName === $newName) {
        throw new InvalidArgumentException('Informe um novo nome diferente do atual.');
    }

    $escapedCurrentName = neo4j_escape_schema_identifier($currentName);
    $escapedNewName = neo4j_escape_schema_identifier($newName);
    $client = neo4j_client();

    match ($type) {
        'nodes' => $client->run(
            'MATCH (node:`' . $escapedCurrentName . '`) ' .
            'REMOVE node:`' . $escapedCurrentName . '` ' .
            'SET node:`' . $escapedNewName . '` ' .
            'RETURN count(node) AS total'
        ),
        'relationships' => $client->run(
            'MATCH (start)-[relationship:`' . $escapedCurrentName . '`]->(end) ' .
            'CREATE (start)-[renamedRelationship:`' . $escapedNewName . '`]->(end) ' .
            'SET renamedRelationship = properties(relationship) ' .
            'DELETE relationship ' .
            'RETURN count(renamedRelationship) AS total'
        ),
        'properties' => $client->run(
            'CALL { ' .
            'MATCH (entity) WHERE entity.`' . $escapedCurrentName . '` IS NOT NULL ' .
            'SET entity.`' . $escapedNewName . '` = entity.`' . $escapedCurrentName . '` ' .
            'REMOVE entity.`' . $escapedCurrentName . '` ' .
            'RETURN count(entity) AS nodeTotal ' .
            '} ' .
            'CALL { ' .
            'MATCH ()-[relationship]->() WHERE relationship.`' . $escapedCurrentName . '` IS NOT NULL ' .
            'SET relationship.`' . $escapedNewName . '` = relationship.`' . $escapedCurrentName . '` ' .
            'REMOVE relationship.`' . $escapedCurrentName . '` ' .
            'RETURN count(relationship) AS relationshipTotal ' .
            '} ' .
            'RETURN nodeTotal, relationshipTotal'
        ),
        default => throw new InvalidArgumentException('Tipo de estrutura inválido.'),
    };
}

/**
 * Deletes a node label, relationship type or property key from Neo4j.
 */
function neo4j_delete_schema_item(string $type, string $name): void
{
    if (!class_exists(ClientBuilder::class)) {
        throw new RuntimeException('Dependências ausentes. Execute `composer install` para instalar laudis/neo4j-php-client.');
    }

    $name = neo4j_clean_schema_name($name);
    $escapedName = neo4j_escape_schema_identifier($name);
    $client = neo4j_client();

    match ($type) {
        'nodes' => $client->run(
            'MATCH (node:`' . $escapedName . '`) ' .
            'DETACH DELETE node ' .
            'RETURN count(node) AS total'
        ),
        'relationships' => $client->run(
            'MATCH ()-[relationship:`' . $escapedName . '`]->() ' .
            'DELETE relationship ' .
            'RETURN count(relationship) AS total'
        ),
        'properties' => $client->run(
            'CALL { ' .
            'MATCH (entity) WHERE entity.`' . $escapedName . '` IS NOT NULL ' .
            'REMOVE entity.`' . $escapedName . '` ' .
            'RETURN count(entity) AS nodeTotal ' .
            '} ' .
            'CALL { ' .
            'MATCH ()-[relationship]->() WHERE relationship.`' . $escapedName . '` IS NOT NULL ' .
            'REMOVE relationship.`' . $escapedName . '` ' .
            'RETURN count(relationship) AS relationshipTotal ' .
            '} ' .
            'RETURN nodeTotal, relationshipTotal'
        ),
        default => throw new InvalidArgumentException('Tipo de estrutura inválido.'),
    };
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
                'MATCH (node) '
                . 'WITH labels(node) AS nodeLabels '
                . 'UNWIND nodeLabels AS label '
                . 'WITH DISTINCT label '
                . 'WHERE NOT label IN $internalNames '
                . 'RETURN label ORDER BY label',
                'label',
                ['internalNames' => neo4j_internal_schema_names()]
            ),
            'relationships' => neo4j_fetch_column(
                $client,
                'MATCH ()-[relationship]->() '
                . 'WITH DISTINCT type(relationship) AS relationshipType '
                . 'WHERE NOT relationshipType IN $internalNames '
                . 'RETURN relationshipType ORDER BY relationshipType',
                'relationshipType',
                ['internalNames' => neo4j_internal_schema_names()]
            ),
            'properties' => neo4j_fetch_column(
                $client,
                'CALL { '
                . 'MATCH (entity) '
                . 'UNWIND keys(entity) AS propertyKey '
                . 'RETURN propertyKey '
                . 'UNION '
                . 'MATCH ()-[relationship]->() '
                . 'UNWIND keys(relationship) AS propertyKey '
                . 'RETURN propertyKey '
                . '} '
                . 'WITH DISTINCT propertyKey '
                . 'WHERE NOT propertyKey IN $internalNames '
                . 'RETURN propertyKey ORDER BY propertyKey',
                'propertyKey',
                ['internalNames' => neo4j_internal_schema_names()]
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
 * Removes registry-only labels, relationships and properties from user-facing schema lists.
 *
 * @param list<string> $names
 * @return list<string>
 */
function neo4j_without_internal_schema_names(array $names): array
{
    $internalNames = neo4j_internal_schema_names();

    return array_values(array_filter($names, static fn (string $name): bool => !in_array($name, $internalNames, true)));
}

/**
 * Returns the hidden names used only to keep newly-created schema items visible.
 *
 * @return list<string>
 */
function neo4j_internal_schema_names(): array
{
    return [
        NEO4J_SCHEMA_REGISTRY_LABEL,
        NEO4J_SCHEMA_REGISTRY_RELATIONSHIP,
        NEO4J_SCHEMA_REGISTRY_ID_PROPERTY,
        NEO4J_SCHEMA_REGISTRY_VALUE_PROPERTY,
    ];
}

/**
 * Escapes values for safe HTML output.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
