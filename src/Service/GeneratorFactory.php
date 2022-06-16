<?php declare(strict_types=1);

namespace DrupalCodeGenerator\Service;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use DrupalCodeGenerator\Application;
use Psr\Log\LoggerInterface;

/**
 * Defines generator factory.
 *
 * @internal
 */
final class GeneratorFactory {

  private ClassResolverInterface $classResolver;
  private LoggerInterface $logger;

  /**
   * The object constructor.
   */
  public function __construct(ClassResolverInterface $class_resolver, LoggerInterface $logger) {
    $this->classResolver = $class_resolver;
    $this->logger = $logger;
  }

  /**
   * Finds and instantiates generator commands.
   *
   * @param string[] $directories
   *   Directories to look up for commands.
   * @param string $namespace
   *   The namespace to filter out commands.
   *
   * @return \Symfony\Component\Console\Command\Command[]
   *   Array of generators.
   */
  public function getGenerators(array $directories, string $namespace): array {
    $commands = [];

    foreach ($directories as $directory) {
      $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
      );
      foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
          continue;
        }

        $sub_path = $iterator->getInnerIterator()->getSubPath();
        $sub_namespace = $sub_path ? \str_replace(\DIRECTORY_SEPARATOR, '\\', $sub_path) . '\\' : '';
        $class = $namespace . '\\' . $sub_namespace . $file->getBasename('.php');

        // Legacy generators can throw fatal errors.
        try {
          $reflected_class = new \ReflectionClass($class);
        }
        catch (\Throwable $exception) {
          $this->logger->notice(
            'Could not load generator {class}.' . \PHP_EOL . '{error}',
            ['class' => $class, 'error' => $exception->getMessage()],
          );
          continue;
        }

        if ($reflected_class->isInterface() || $reflected_class->isAbstract() || $reflected_class->isTrait()) {
          continue;
        }

        $commands[] = $this->classResolver->getInstanceFromDefinition($class);
      }
    }

    $this->logger->debug('Total generators: {total}', ['total' => \count($commands)]);
    return $commands;
  }

  /**
   * Finds and instantiates DCG core generator commands.
   *
   * @todo Remove this.
   */
  public function getCoreGenerators(): array {
    return $this->getGenerators([Application::ROOT . '/src/Command'], '\DrupalCodeGenerator\Command');
  }

}
