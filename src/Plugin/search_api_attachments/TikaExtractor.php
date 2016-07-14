<?php

/**
 * @file
 * Contains \Drupal\search_api_attachments\Plugin\search_api_attachments\TikaExtractor.
 */

namespace Drupal\search_api_attachments\Plugin\search_api_attachments;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api_attachments\TextExtractorPluginBase;

/**
 * Provides tika extractor.
 *
 * @SearchApiAttachmentsTextExtractor(
 *   id = "tika_extractor",
 *   label = @Translation("Tika Extractor"),
 *   description = @Translation("Adds Tika extractor support."),
 * )
 */
class TikaExtractor extends TextExtractorPluginBase {

  /**
   * Extract file with Tika library.
   *
   * @param object $file
   *   A file object.
   *
   * @return string
   *   The text extracted from the file.
   */
  public function extract($file) {
    $filepath = $this->getRealpath($file->getFileUri());
    $tika = realpath($this->configuration['tika_path']);
    $java = $this->configuration['java_path'];
    // UTF-8 multibyte characters will be stripped by escapeshellargs() for the
    // default C-locale.
    // So temporarily set the locale to UTF-8 so that the filepath remains
    // valid.
    $backup_locale = setlocale(LC_CTYPE, '0');
    setlocale(LC_CTYPE, 'en_US.UTF-8');
    $param = '';
    if ($file->getMimeType() != 'audio/mpeg') {
      $param = ' -Dfile.encoding=UTF8 -cp ' . escapeshellarg($tika);
    }

    // Force running the Tika jar headless.
    $param = ' -Djava.awt.headless=true ' . $param;

    $cmd = $java . $param . ' -jar ' . escapeshellarg($tika) . ' -t ' . escapeshellarg($filepath);
    if (strpos(ini_get('extension_dir'), 'MAMP/')) {
      $cmd = 'export DYLD_LIBRARY_PATH=""; ' . $cmd;
    }
    // Restore the locale.
    setlocale(LC_CTYPE, $backup_locale);
    // Support UTF-8 commands:
    // @see http://www.php.net/manual/en/function.shell-exec.php#85095
    shell_exec("LANG=en_US.utf-8");
    return shell_exec($cmd);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['java_path'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Path to java executable'),
      '#description' => $this->t('Enter the path to java executable. Example: "java".'),
      '#default_value' => $this->configuration['java_path'],
      '#required' => TRUE,
    );
    $form['tika_path'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Path to Tika .jar file'),
      '#description' => $this->t('Enter the full path to tika executable jar file. Example: "/var/apache-tika/tika-app-1.8.jar".'),
      '#default_value' => $this->configuration['tika_path'],
      '#required' => TRUE,
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    // Check java path.
    if (isset($values['text_extractor_config']['java_path']) && !empty($values['text_extractor_config']['java_path'])) {
      $java = $values['text_extractor_config']['java_path'];
      exec($java, $output, $return_code);
      // $return_code = 127 if it fails. 1 instead.
      if ($return_code != 1 && isset($form['text_extractor_config']['java_path'])) {
        $form_state->setError($form['text_extractor_config']['java_path'], $this->t('Invalid path or filename %path for java executable.', array('%path' => $values['text_extractor_config']['java_path'])));
        return;
      }
    }
    elseif (isset($form['text_extractor_config']['java_path'])) {
      $form_state->setError($form['text_extractor_config']['java_path'], $this->t('You must set a valid path to be able to check tika application jar.', array('%path' => $values['text_extractor_config']['java_path'])));
      return;
    }

    // Check tika path.
    if (isset($values['text_extractor_config']['tika_path']) && !empty($values['text_extractor_config']['tika_path'])) {
      if (!file_exists($values['text_extractor_config']['tika_path']) && isset($form['text_extractor_config']['tika_path'])) {
        $form_state->setError($form['text_extractor_config']['tika_path'], $this->t('Invalid path or filename %path for tika application jar.', array('%path' => $values['text_extractor_config']['tika_path'])));
      }
      else {
        $cmd = $java . ' -jar ' . escapeshellarg($values['text_extractor_config']['tika_path']) . ' -V';
        exec($cmd, $output, $return_code);
        // $return_code = 1 if it fails. 0 instead.
        if ($return_code && isset($form['text_extractor_config']['tika_path'])) {
          $form_state->setError($form['text_extractor_config']['tika_path'], $this->t('Tika could not be reached and executed.'));
        }
        else {
          drupal_set_message(t('Tika can be reached and be executed'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['java_path'] = $form_state->getValue(array('text_extractor_config', 'java_path'));
    $this->configuration['tika_path'] = $form_state->getValue(array('text_extractor_config', 'tika_path'));
    parent::submitConfigurationForm($form, $form_state);
  }

}