<?php
/**
 * Copyright: STORY DESIGN Sp. z o.o.
 * Author: Yaroslav Shatkevich
 * Date: 29.07.2015
 * Time: 10:09
 */

namespace TranslateExtractor\Controller;


use Zend\Console\Console;
use Zend\Console\Request as ConsoleRequest;
use Zend\EventManager\Exception\DomainException;
use Zend\Http\Client;
use Zend\Http\ClientStatic;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;

class IndexController extends AbstractActionController
{
    private $collectedPaths = [];
    private $collectedTerms = [];
    private $configuration;

    /**
     * Execute the request
     *
     * @param  MvcEvent $e
     * @return mixed
     * @throws DomainException
     */
    public function onDispatch(MvcEvent $e)
    {
        $this->configuration = $e->getApplication()->getServiceManager()->get('config')['translate_extractor'];

        return parent::onDispatch($e);
    }

    public function updateAction()
    {
        $this->consoleLog('Generate .mo file on POEditor... ', false);
        $response = $this->apiCall('export', ['language' => $this->params('lang'), 'type' => 'mo']);
        $this->consoleLog('Success');

        $this->consoleLog('Downloading .mo file from POEditor... ', false);
        $exportFileUrl = $response['item'];
        $client = new Client($exportFileUrl);
        $client->setStream($this->configuration['translations_path'] . DIRECTORY_SEPARATOR . $this->params('locale') . '.mo')
            ->setOptions(['sslverifypeer' => false])
            ->send();

        if ($client->getResponse()->isOk()) {
            $this->consoleLog('Success');
            $this->consoleLog('Translation updated successfully!');
        } else {
            $this->consoleLog('Fail');
        }
    }

    public function extractAction()
    {
        $this->consoleLog('Scanning module directory for translatable files... ', false);
        $this->readDir('module');
        $this->consoleLog(count($this->collectedPaths) . ' files found');

        $this->consoleLog('Processing files... ', false);
        $this->processFiles();
        $this->consoleLog(count($this->collectedTerms) . ' terms found');

        $this->consoleLog('Sync terms with POEditor... ', false);
        list($parsed, $added, $deleted) = $this->syncTerms();

        if ($parsed) {
            $this->consoleLog(sprintf('%d terms parsed, %d added and %d deleted', $parsed, $added, $deleted));
            $this->consoleLog('Synchronization finished successfully');
        } else {
            $this->consoleLog('Fail');
        }
    }

    private function consoleLog($message, $withEol = true)
    {
        $request = $this->getRequest();

        if ($request instanceof ConsoleRequest) {
            Console::getInstance()->write($message . ($withEol ? PHP_EOL : ''));
        } else {
            echo $message . ($withEol ? '<br>' : '');
        }
    }

    private function syncTerms()
    {
        $data = [];
        foreach ($this->collectedTerms as $value) {
            $data[] = [
                'term' => $value,
                'context' => '',
                'reference' => '',
                'plural' => '',
                'comment' => '',
            ];
        }

        $response = $this->apiCall('sync_terms', ['data' => json_encode($data)]);

        return [
            $response['details']['parsed'],
            $response['details']['added'],
            $response['details']['deleted'],
        ];
    }

    private function processFiles()
    {
        foreach ($this->collectedPaths as $twigFile) {
            $content = file_get_contents($twigFile);
            preg_match_all('/(?:translate|_)\(\'(.+?)\'\)/', $content, $matches);
            foreach ($matches[1] as $matchedString) {
                $this->collectedTerms[] = stripslashes($matchedString);
            }
        }
        $this->collectedTerms = array_unique($this->collectedTerms);
    }

    private function readDir($path)
    {
        if (in_array($path, $this->configuration['exclude_paths'])) {
            return;
        }

        if (is_dir($path)) {
            $dir_handle = opendir($path);
            while (false !== ($item = readdir($dir_handle))) {
                if ($item != '.' && $item != '..') {
                    $itemPath = $path . DIRECTORY_SEPARATOR . $item;
                    if (is_dir($itemPath)) {
                        $this->readDir($itemPath);
                    } else {
                        $ext = pathinfo($itemPath, PATHINFO_EXTENSION);
                        if (in_array($ext, ['twig', 'php', 'phtml'])) {
                            $this->collectedPaths[] = $itemPath;
                        }
                    }
                }
            }
            closedir($dir_handle);
        }
    }

    private function apiCall($action, array $params = array())
    {
        $params = array_merge([
            'api_token' => $this->configuration['poeditor']['token'],
            'id' => $this->configuration['poeditor']['project_id'],
            'action' => $action,
        ], $params);

        $response = ClientStatic::post('https://poeditor.com/api/', $params, [], null, [
            'sslverifypeer' => false
        ]);

        $responseData = json_decode($response->getBody(), true);

        if (isset($responseData['response']) && isset($responseData['response']['status']) && $responseData['response']['status'] === 'success') {
            return $responseData;
        }

        throw new \Exception('Api call error: ' . $response->getBody());
    }
}