<?php

namespace CtSearchBundle\Processor;

class MatchingListFilter extends ProcessorFilter {

  public function getDisplayName() {
    return "Matching list filter";
  }

  public function getSettingsForm($controller) {
    $matchingLists = $this->getIndexManager()->getMatchingLists();
    $choices = array(
      '' => 'Select a matching list',
    );
    foreach ($matchingLists as $list) {
      $choices[$list->getId()] = $list->getName();
    }
    $formBuilder = parent::getSettingsForm($controller)
        ->add('setting_matching_list', 'choice', array(
          'required' => true,
          'choices' => $choices,
          'label' => $controller->get('translator')->trans('Matching list'),
        ))
        ->add('setting_case_insensitive', 'checkbox', array(
          'required' => false,
          'label' => $controller->get('translator')->trans('Case insentive input'),
        ))
        ->add('setting_default_value', 'text', array(
          'required' => false,
          'trim' => false,
          'label' => $controller->get('translator')->trans('Default value'),
        ))
        ->add('ok', 'submit', array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }

  public function getFields() {
    return array('output');
  }

  public function getArguments() {
    return array('input' => 'Input');
  }

  public function execute(&$document) {
    $settings = $this->getSettings();
    $input = $this->getArgumentValue('input', $document);
    $output = null;
    if (!empty($input)) {
      if (is_array($input))
        $data = $input;
      else
        $data = array($input);
      $output = array();
      $list = json_decode(json_encode($this->getIndexManager()->getMatchingList($settings['matching_list'])->getList()), true);
      foreach ($data as $in) {
        $found = false;
        $out = '';
        if (is_string($in) && !empty($in)) {
          foreach ($list as $k => $v) {
            if ($settings['case_insensitive']) {
              if (strtolower($k) == strtolower($in)) {
                $found = true;
                $out = $v;
              }
            } else {
              if ($k == $in) {
                $found = true;
                $out = $v;
              }
            }
          }
        }
        /*if ($this->getOutput() != null) {
          $this->getOutput()->writeln('>> Value "' . $in . '" was found ==> ' . $found);
        }*/
        if ($found) {
          if (!empty($out) && !in_array($out, $output)) {
            $output[] = $out;
          }
        } else {
          if (!empty($settings['default_value'])) {
            if (strtolower($settings['default_value']) != 'null' && !in_array($settings['default_value'], $output)) {
              $output[] = $settings['default_value'];
            }
          } else {
            if (!in_array($in, $output)) {
              $output[] = $in;
            }
          }
        }
      }
      unset($list);
      if (count($output) == 0) {
        $output = null;
      } elseif (count($output) == 1) {
        $output = $output[0];
      }
    }
    /*if ($this->getOutput() != null) {
      $ret = $output;
      if($ret == null)
        $ret = 'null';
      elseif(is_array($ret))
        $ret = implode(', ', $ret);
      $this->getOutput()->writeln('>> Return "' . $ret . '"');
    }*/
    return array('output' => $output);
  }

}
