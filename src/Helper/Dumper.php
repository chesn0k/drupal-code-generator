<?php

namespace DrupalCodeGenerator\Helper;

use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Output dumper form generators.
 */
class Dumper extends Helper {

  /**
   * The file system utility.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  public $filesystem;

  /**
   * Input instance.
   *
   * @var \Symfony\Component\Console\Input\InputInterface
   */
  protected $input;

  /**
   * Output instance.
   *
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

  /**
   * Replace flag.
   *
   * @var bool
   */
  protected $replace;

  /**
   * Constructs a generator command.
   *
   * @param \Symfony\Component\Filesystem\Filesystem $filesystem
   *   The file system utility.
   * @param bool $replace
   *   (optional) Indicates weather or not existing files can be replaced.
   */
  public function __construct(Filesystem $filesystem, $replace = NULL) {
    $this->filesystem = $filesystem;
    $this->replace = $replace;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() :string {
    return 'dcg_dumper';
  }

  /**
   * Dumps the generated code to file system.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   Input instance.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Output instance.
   *
   * @return array
   *   List of created or updated files.
   */
  public function dump(InputInterface $input, OutputInterface $output) :array {
    $this->input = $input;
    $this->output = $output;
    $formatter_style = new OutputFormatterStyle('black', 'cyan', []);
    $this->output->getFormatter()->setStyle('title', $formatter_style);

    $interactive = $input->isInteractive();

    // NULL means we should ask user for confirmation.
    if ($this->replace !== NULL) {
      $input->setInteractive(FALSE);
    }

    /** @var \DrupalCodeGenerator\Command\GeneratorInterface $command */
    $command = $this->getHelperSet()->getCommand();

    $dumped_files = $this->doDump($command->getAssets(), $command->getDirectory());

    $input->setInteractive($interactive);
    return $dumped_files;
  }

  /**
   * Dumps assets.
   *
   * @param \DrupalCodeGenerator\Asset[] $assets
   *   Files to dump.
   * @param string $directory
   *   Directory where to dump the assets.
   *
   * @return array
   *   List of created or updated assets.
   */
  protected function doDump(array $assets, string $directory) :array {
    $dumped_files = [];

    foreach ($assets as $asset) {

      $content = $asset->getContent();
      $path = $asset->getPath();
      $file_path = "$directory/$path";
      if ($this->filesystem->exists($file_path) && !$asset->isDirectory()) {
        $action = $asset->getAction();
        if ($action == 'replace') {
          $question_text = "The file <comment>$file_path</comment> already exists. Would you like to replace it?";
          $question = new ConfirmationQuestion($question_text, $this->replace !== FALSE);
          /** @var \DrupalCodeGenerator\Helper\QuestionHelper $question_helper */
          $question_helper = $this->getHelperSet()->get('question');
          if (!$question_helper->ask($this->input, $this->output, $question)) {
            continue;
          }
        }
        else {
          $original_content = file_get_contents($file_path);
          if ($action == 'append') {
            $header_size = $asset->getHeaderSize();
            // Do not remove header if original file is empty.
            if ($original_content && $header_size > 0) {
              $content = Utils::removeHeader($content, $header_size);
            }
            $content = $original_content . "\n" . $content;
          }
          elseif (is_callable($action)) {
            $content = $action($original_content, $content);
          }
          else {
            throw new \LogicException("Unsupported action: $action.");
          }
        }
      }

      // Default mode for all parent directories is 0777. It can be modified by
      // changing umask.
      $mode = $asset->getMode();

      // Save data to file system.
      if ($asset->isDirectory()) {
        $this->filesystem->mkdir([$file_path], $mode);
      }
      else {
        $this->filesystem->dumpFile($file_path, $content);
        $this->filesystem->chmod($file_path, $mode);
      }

      $dumped_files[] = $asset->getPath();
    }

    return $dumped_files;
  }

}
