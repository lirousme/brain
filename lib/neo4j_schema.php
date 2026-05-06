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
 * Creates a node label, relationship type or property key immediately in Neo4j.
 */
function neo4j_create_schema_item(string $type, string $name): void
{
    if (!class_exists(ClientBuilder::class)) {
        throw new RuntimeException('Dependências ausentes. Execute `composer install` para instalar laudis/neo4j-php-client.');
    }

    $name = neo4j_clean_schema_name($name);
    $escapedName = neo4j_escape_schema_identifier($name);
    $client = neo4j_client();

    match ($type) {
        'nodes' => $client->run('MERGE (n:`' . $escapedName . '`)'),
        'relationships' => $client->run(
            'OPTIONAL MATCH (existing) WITH existing LIMIT 1 ' .
            'CALL { ' .
            'WITH existing WITH existing WHERE existing IS NOT NULL RETURN existing AS node ' .
            'UNION ' .
            'WITH existing WITH existing WHERE existing IS NULL CREATE (created) RETURN created AS node ' .
            '} ' .
            'MERGE (node)-[relationship:`' . $escapedName . '`]->(node) ' .
            'RETURN count(relationship) AS total'
        ),
        'properties' => $client->run(
            'OPTIONAL MATCH (existing) WITH existing LIMIT 1 ' .
            'CALL { ' .
            'WITH existing WITH existing WHERE existing IS NOT NULL RETURN existing AS node ' .
            'UNION ' .
            'WITH existing WITH existing WHERE existing IS NULL CREATE (created) RETURN created AS node ' .
            '} ' .
            'SET node.`' . $escapedName . '` = coalesce(node.`' . $escapedName . '`, true) ' .
            'RETURN node.`' . $escapedName . '` AS propertyValue'
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
            'REMOVE node:`' . $escapedName . '` ' .
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
