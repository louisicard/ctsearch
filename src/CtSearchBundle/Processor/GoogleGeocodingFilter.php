<?php
namespace CtSearchBundle\Processor;

class GoogleGeocodingFilter extends ProcessorFilter
{


  public function getDisplayName()
  {
    return "Google Geocoding Filter";
  }

  public function getSettingsForm($controller)
  {
    $formBuilder = parent::getSettingsForm($controller)
      ->add('setting_api_key', 'text', array(
        'required' => false,
        'label' => $controller->get('translator')->trans('API key'),
      ))
      ->add('ok', 'submit', array('label' => $controller->get('translator')->trans('OK')));
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
        if (isset($json['status']) && $json['status'] == 'OK' && isset($json['results'][0]['geometry']['location']['lat'])) {
          usleep(100000);//Sleep for 100ms
          return array('location' => array(
            'lat' => $json['results'][0]['geometry']['location']['lat'],
            'lon' => $json['results'][0]['geometry']['location']['lng'],
          ));
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
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode($r, true);
  }

}
