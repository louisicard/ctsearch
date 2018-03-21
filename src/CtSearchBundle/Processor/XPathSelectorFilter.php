<?php


namespace CtSearchBundle\Processor;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class XPathSelectorFilter extends ProcessorFilter {
  
  
  public function getDisplayName() {
    return "CSS selector to XML";
  }

  public function getSettingsForm($controller) {
    $formBuilder = parent::getSettingsForm($controller)
      ->add('setting_selector', TextType::class, array(
        'required' => true,
        'label' => $controller->get('translator')->trans('CSS selector'),
      ))
      ->add('setting_notidy', CheckboxType::class, array(
        'required' => false,
        'label' => $controller->get('translator')->trans('No Tidy'),
      ))
      ->add('ok', SubmitType::class, array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }

  public function getFields() {
    return array('xml_parts');
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
        $cleanHtml = !isset($settings['notidy']) || !$settings['notidy'] ? tidy_repair_string($html, $options, 'utf8') : $html;
        $dom->loadHTML(mb_convert_encoding($cleanHtml, 'HTML-ENTITIES', 'UTF-8'));
      }catch(\Exception $ex){}
      $this->xpath = new \DOMXPath($dom);
      return array('xml_parts' => $this->selectToXML($selector)); 
    }
  }
  
  public function select($selector, $as_array = true) {
    $cssSelector = new \Symfony\Component\CssSelector\CssSelectorConverter();
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