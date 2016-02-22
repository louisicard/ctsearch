<?php

namespace CtSearchBundle\Processor;

class XPathFinderFilter extends ProcessorFilter {

  public function getDisplayName() {
    return "Xpath finder Parser";
  }

  public function getSettingsForm($controller) {
    $formBuilder = parent::getSettingsForm($controller)
        ->add('setting_xpath', 'text', array(
          'required' => true,
          'label' => $controller->get('translator')->trans('Xpath'),
        ))
        ->add('ok', 'submit', array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }

  public function getFields() {
    return array('output');
  }

  public function getArguments() {
    return array(
      'xml_xpath' => 'XML xpath document',
    );
  }

  public function execute(&$document) {
    try {
      $xpath = $this->getArgumentValue('xml_xpath', $document);
      $settings = $this->getSettings();
      $query = $settings['xpath'];
      $queries = array_map('trim', explode(',', $query));
      if ($xpath != null) {
        $r = array();
        foreach ($queries as $query) {
          for ($i = 0; $i < $xpath->query($query)->length; $i++) {

            $r[] = $xpath->query($query)->item($i)->textContent;
          }
        }
        //var_dump($xpath->evaluate('concat(vendor:record/vendor:datafield[@tag=\'702\']/vendor:subfield[@code=\'a\'], \' \', vendor:record/vendor:datafield[@tag=\'702\']/vendor:subfield[@code=\'b\'])'));
        unset($xpath);
        unset($settings);
        unset($query);
        unset($queries);

        gc_enable();
        gc_collect_cycles();
        return array('output' => $r);
      }
    } catch (\Exception $ex) {
      var_dump($ex);
    }
    return array('output' => array());
  }

}
