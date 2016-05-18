<?php


namespace CtSearchBundle\Processor;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class XPathSelectorAttributesFilter extends ProcessorFilter {
  
  
  public function getDisplayName() {
    return "CSS selector to XML attributes";
  }

  public function getSettingsForm($controller) {
    $formBuilder = parent::getSettingsForm($controller)
        ->add('setting_selector', TextType::class, array(
          'required' => true,
          'label' => $controller->get('translator')->trans('CSS selector'),
        ))
      ->add('ok', SubmitType::class, array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }

  public function getFields() {
    return array('attributes');
  }

  public function getArguments() {
    return array(
      'html' => 'HTML',
    );
  }
  
  public function execute(&$document) {
    $html = $this->getArgumentValue('html', $document);
    $settings = $this->getSettings();
    $selector = isset($settings['selector']) ? $settings['selector'] : '';
    if($selector == ''){
      return array('xml_parts' => array($html));
    }
    else{
      $options = array(
          'hide-comments' => true,
          'tidy-mark' => false,
          'indent' => true,
          'indent-spaces' => 4,
          'new-blocklevel-tags' => 'article,header,footer,section,nav,figure',
          'new-inline-tags' => 'video,audio,canvas,ruby,rt,rp,time',
          'vertical-space' => false,
          'output-xhtml' => true,
          'wrap' => 0,
          'wrap-attributes' => false,
          'break-before-br' => false,
          'vertical-space' => false,
      );
      $dom = new \DOMDocument();
      try{
        $dom->loadHTML(mb_convert_encoding(tidy_repair_string($html, $options, 'utf8'), 'HTML-ENTITIES', 'UTF-8'));
      }catch(\Exception $ex){}
      $this->xpath = new \DOMXPath($dom);
      $elements = $this->select($selector, false);
      $attributes = array();
      foreach($elements as $elem){
        foreach($elem->attributes as $name => $attrNode)
          $attributes[$name] = $attrNode->textContent;
      }
      return array('attributes' => $attributes); 
    }
  }
  
  public function select($selector, $as_array = true) {
    $cssSelector = new \Symfony\Component\CssSelector\CssSelector();
    $elements = $this->xpath->evaluate($cssSelector->toXPath($selector));
    return $as_array ? elements_to_array($elements) : $elements;
  }
  
  public function selectToXML($selector){
    $elements = $this->select($selector, false);
    $xmlParts = array();
    foreach($elements as $elem){
      $xmlParts[] = simplexml_import_dom($elem)->asXML();
    }
    return $xmlParts;
  }
}

/**
 * Convert $elements to an array.
 */

function elements_to_array($elements) {
  $array = array();
  for ($i = 0, $length = $elements->length; $i < $length; ++$i)
    if ($elements->item($i)->nodeType == XML_ELEMENT_NODE)
      array_push($array, element_to_array($elements->item($i)));
  return $array;
}

/**
 * Convert $element to an array.
 */

function element_to_array($element) {
  $array = array(
    'name' => $element->nodeName,
    'attributes' => array(),
    'text' => $element->textContent,
    'children' =>elements_to_array($element->childNodes)
    );
  if ($element->attributes->length)
    foreach($element->attributes as $key => $attr)
      $array['attributes'][$key] = $attr->value;
  return $array;
}
