<?php

// autoload_classmap.php @generated by Composer

$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);

return array(
    'PhpIntegrator\\Application' => $baseDir . '/src/Application.php',
    'PhpIntegrator\\Application\\Command' => $baseDir . '/src/Application/Command.php',
    'PhpIntegrator\\Application\\CommandInterface' => $baseDir . '/src/Application/CommandInterface.php',
    'PhpIntegrator\\Application\\Command\\ClassInfo' => $baseDir . '/src/Application/Command/ClassInfo.php',
    'PhpIntegrator\\Application\\Command\\ClassList' => $baseDir . '/src/Application/Command/ClassList.php',
    'PhpIntegrator\\Application\\Command\\GlobalConstants' => $baseDir . '/src/Application/Command/GlobalConstants.php',
    'PhpIntegrator\\Application\\Command\\GlobalFunctions' => $baseDir . '/src/Application/Command/GlobalFunctions.php',
    'PhpIntegrator\\Application\\Command\\Reindex' => $baseDir . '/src/Application/Command/Reindex.php',
    'PhpIntegrator\\DocParser' => $baseDir . '/src/DocParser.php',
    'PhpIntegrator\\IndexDataAdapter' => $baseDir . '/src/IndexDataAdapter.php',
    'PhpIntegrator\\IndexDataAdapter\\ClassListProxyProvider' => $baseDir . '/src/IndexDataAdapter/ClassListProxyProvider.php',
    'PhpIntegrator\\IndexDataAdapter\\ProviderInterface' => $baseDir . '/src/IndexDataAdapter/ProviderInterface.php',
    'PhpIntegrator\\IndexDatabase' => $baseDir . '/src/IndexDatabase.php',
    'PhpIntegrator\\IndexStorageItemEnum' => $baseDir . '/src/IndexStorageItemEnum.php',
    'PhpIntegrator\\Indexer' => $baseDir . '/src/Indexer.php',
    'PhpIntegrator\\Indexer\\DependencyFetchingVisitor' => $baseDir . '/src/Indexer/DependencyFetchingVisitor.php',
    'PhpIntegrator\\Indexer\\IndexingFailedException' => $baseDir . '/src/Indexer/IndexingFailedException.php',
    'PhpIntegrator\\Indexer\\OutlineIndexingVisitor' => $baseDir . '/src/Indexer/OutlineIndexingVisitor.php',
    'PhpIntegrator\\Indexer\\StorageInterface' => $baseDir . '/src/Indexer/StorageInterface.php',
    'PhpIntegrator\\Indexer\\UseStatementFetchingVisitor' => $baseDir . '/src/Indexer/UseStatementFetchingVisitor.php',
    'PhpIntegrator\\TypeResolver' => $baseDir . '/src/TypeResolver.php',
);
