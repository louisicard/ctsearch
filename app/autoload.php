<?php

use Doctrine\Common\Annotations\AnnotationRegistry;
use Composer\Autoload\ClassLoader;

/**
 * @var ClassLoader $loader
 */
$loader = require __DIR__.'/../vendor/autoload.php';

AnnotationRegistry::registerLoader(array($loader, 'loadClass'));

require_once __DIR__ . '/../src/CtSearchBundle/Datasource/Datasource.php';
require_once __DIR__ . '/../src/CtSearchBundle/Datasource/WebCrawler.php';
require_once __DIR__ . '/../src/CtSearchBundle/Datasource/JSONParser.php';
require_once __DIR__ . '/../src/CtSearchBundle/Datasource/OAIHarvester.php';
require_once __DIR__ . '/../src/CtSearchBundle/Datasource/lib/cmis_repository_wrapper.php';
require_once __DIR__ . '/../src/CtSearchBundle/Datasource/lib/cmis_service.php';
require_once __DIR__ . '/../src/CtSearchBundle/Datasource/DrupalCtExport.php';
require_once __DIR__ . '/../src/CtSearchBundle/Datasource/CMISHarvester.php';
require_once __DIR__ . '/../src/CtSearchBundle/Datasource/XMLParser.php';
require_once __DIR__ . '/../src/CtSearchBundle/Processor/ProcessorFilter.php';
require_once __DIR__ . '/../src/CtSearchBundle/Processor/DefineConstantFilter.php';
require_once __DIR__ . '/../src/CtSearchBundle/Processor/HTMLTextExtractorFilter.php';
require_once __DIR__ . '/../src/CtSearchBundle/Processor/ConcatenateFilter.php';
require_once __DIR__ . '/../src/CtSearchBundle/Processor/PHPFilter.php';
require_once __DIR__ . '/../src/CtSearchBundle/Processor/XPathSelectorAttributesFilter.php';
require_once __DIR__ . '/../src/CtSearchBundle/Processor/XPathSelectorFilter.php';
require_once __DIR__ . '/../src/CtSearchBundle/Processor/XPathFinderFilter.php';
require_once __DIR__ . '/../src/CtSearchBundle/Processor/ArrayImplodeFilter.php';
require_once __DIR__ . '/../src/CtSearchBundle/Processor/XMLParserFilter.php';
require_once __DIR__ . '/../src/CtSearchBundle/Processor/MatchingListFilter.php';
require_once __DIR__ . '/../src/CtSearchBundle/Processor/AssociativeArraySelectorFilter.php';
require_once __DIR__ . '/../src/CtSearchBundle/Processor/XMLParserToArrayFilter.php';

return $loader;
