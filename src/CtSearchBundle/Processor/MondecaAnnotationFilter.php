<?php
namespace CtSearchBundle\Processor;

use CtSearchBundle\Classes\CurlUtils;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class MondecaAnnotationFilter extends ProcessorFilter
{


  public function getDisplayName()
  {
    return "Mondeca Annotation Filter";
  }

  public function getSettingsForm($controller)
  {
    $formBuilder = parent::getSettingsForm($controller)
      ->add('setting_api_url', TextType::class, array(
        'required' => false,
        'label' => $controller->get('translator')->trans('API url'),
      ))
      ->add('ok', SubmitType::class, array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }

  public function getFields()
  {
    return array('annotations');
  }

  public function getArguments()
  {
    return array(
      'text' => 'Text to analyze',
    );
  }

  public function execute(&$document)
  {
    try {
      $settings = $this->getSettings();
      $apiUrl = isset($settings['api_url']) ? $settings['api_url'] : '';
      $text = $this->getArgumentValue('text', $document);

      $json = $this->getApiResponse($apiUrl, $text);
      $annotations = [];
      if(isset($json['validKnowledge'])) {
        $xml = $json['validKnowledge'];
        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        $xpath = new \DOMXPath($doc);
        $result = $xpath->query("//namespace::*");
        foreach ($result as $node) {
          if($node->nodeName == 'xmlns'){
            $xpath->registerNamespace('vendor', $node->nodeValue);
          }
        }
        $xx = $xpath->query('//rdfs:label');
        foreach($xx as $x) {
          foreach($x->attributes as $attr) {
            //var_dump($attr->nodeName . ' --> ' . $attr->nodeValue);
            if($attr->nodeName == 'xml:lang' && $attr->nodeValue == 'fr') {
              //var_dump($x->parentNode->nodeName .  ' --> ' . $x->nodeValue);
              if(!isset($annotations[$x->parentNode->nodeName])) {
                $annotations[$x->parentNode->nodeName] = [];
              }
              if(!in_array($x->nodeValue, $annotations[$x->parentNode->nodeName])) {
                $annotations[$x->parentNode->nodeName][] = $x->nodeValue;
              }
            }
          }
        }
      }
      //var_dump($annotations);
      return array('annotations' => $annotations);

    } catch (\Exception $ex) {
      return array('annotations' => null);
    }
  }

  private function getApiResponse($apiUrl, $text)
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
      'name' => 'content-augmentation/processes/processMediaSem-fr.xml',
      'reference' => 'urn:1',
      'content' => $text
    )));
    CurlUtils::handleCurlProxy($ch);
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode($r, true);
  }

}
