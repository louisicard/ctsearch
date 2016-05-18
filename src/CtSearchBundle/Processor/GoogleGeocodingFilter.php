<?php
namespace CtSearchBundle\Processor;

use CtSearchBundle\Classes\CurlUtils;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class GoogleGeocodingFilter extends ProcessorFilter
{


  public function getDisplayName()
  {
    return "Google Geocoding Filter";
  }

  public function getSettingsForm($controller)
  {
    $formBuilder = parent::getSettingsForm($controller)
      ->add('setting_api_key', TextType::class, array(
        'required' => false,
        'label' => $controller->get('translator')->trans('API key'),
      ))
      ->add('ok', SubmitType::class, array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }

  public function getFields()
  {
    return array('location');
  }

  public function getArguments()
  {
    return array(
      'address' => 'Address',
    );
  }

  public function execute(&$document)
  {
    try {
      $settings = $this->getSettings();
      $apiKey = isset($settings['api_key']) ? $settings['api_key'] : '';
      $address = $this->getArgumentValue('address', $document);

      if (!empty($address)) {
        $google_url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address);
        if (!empty($apiKey))
          $google_url .= '&key=' . $apiKey;

        $json = $this->getUrlResponse($google_url);
        if (isset($json['status']) && $json['status'] == 'OK' && isset($json['results'][0])) {
          usleep(100000);//Sleep for 100ms
          return array('location' => $json['results'][0]);
        }
      }
      return array('value' => null);

    } catch (\Exception $ex) {
      return array('value' => null);
    }
  }

  private function getUrlResponse($url)
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    CurlUtils::handleCurlProxy($ch);
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode($r, true);
  }

}
