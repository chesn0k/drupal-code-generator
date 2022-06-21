<?php declare(strict_types=1);

namespace DrupalCodeGenerator\Command;

use DrupalCodeGenerator\Application;
use DrupalCodeGenerator\Asset\AssetCollection;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\GeneratorType;

#[Generator(
  name: 'service-provider',
  description: 'Generates a service provider',
  templatePath: Application::TEMPLATE_PATH . '/service-provider',
  type: GeneratorType::MODULE_COMPONENT,
)]
final class ServiceProvider extends BaseGenerator {

  protected function generate(array &$vars, AssetCollection $assets): void {
    $ir = $this->createInterviewer($vars);
    $vars['machine_name'] = $ir->askMachineName();
    $vars['name'] = $ir->askName();
    // The class names is required to be a CamelCase version of the module's
    // machine name followed by ServiceProvider.
    // @see https://www.drupal.org/node/2026959
    $vars['class'] = '{machine_name|camelize}ServiceProvider';
    $assets->addFile('src/{class}.php', 'service-provider.twig');
  }

}
