<?php

/**
 * @file
 * Contains \Drupal\blood_bank\Form\RegistrationForm.
 */

namespace Drupal\blood_bank\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\taxonomy\Entity\Term;

class SendNotification extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'send_notification_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $vid = 'blood_group';
    /** @var \Drupal\taxonomy\Entity\Term[] $terms */
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid]);
    foreach ($terms as $term) {
      $term_data[$term->id()] = $term->getName();
    }

    $form['blood_group'] = array(
      '#type' => 'select',
      '#title' => ('Select Blood Group:'),
      '#options' => $term_data,
    );
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Send'),
      '#button_type' => 'primary',
    );
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get selected blood group.
    $field = $form_state->getValues();
    $term = Term::load($field['blood_group']);
    $blood_group = $term->getName();

    // Get blood bank details.
    $current_user = User::load(\Drupal::currentUser()->id());
    $blood_bank_details = $current_user->field_blood_bank->entity;
    //kint($blood_bank_details->get('field_telephone')->value);exit;
    // Get authenticated users.
    $userStorage = \Drupal::entityTypeManager()->getStorage('user');
    $query = $userStorage->getQuery();
    $ids = $query
      ->condition('status', '1')
      ->execute();
    $users = User::loadMultiple($ids);

    foreach($users as $user){
      if (count($user->getRoles()) == 1) {
        $usermail = $user->get('mail')->value;

        // Send a mail to every user.
        $this->sendMail($usermail, $blood_group, $blood_bank_details);
      }
    }
  }

  public function sendMail($usermail, $blood_group, $blood_bank_details) {
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'blood_bank';
    $key = 'send_notification';
    $to = $usermail;
    $params['message'] = t('Need a blood of type: @blood_group. You can contact: @blood_bank at @contact', array(
      '@blood_group' => $blood_group,
      '@blood_bank' => $blood_bank_details->getTitle(),
      '@contact' => $blood_bank_details->get('field_telephone')->value
    ));
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = true;

    $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
    if ($result['result'] !== true) {
      \Drupal::messenger()->addMessage(t("There was a problem sending your message and it was not sent."));
    }
    else {
      \Drupal::messenger()->addMessage(t("Your mail has been sent."));
    }
  }
}
