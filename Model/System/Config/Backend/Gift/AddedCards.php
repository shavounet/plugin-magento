<?php
/**
 * PayZen V2-Payment Module version 2.1.1 for Magento 2.x. Support contact : support@payzen.eu.
 *
 * NOTICE OF LICENSE
 *
 * This source file is licensed under the Open Software License version 3.0
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category  payment
 * @package   payzen
 * @author    Lyra Network (http://www.lyra-network.com/)
 * @copyright 2014-2016 Lyra Network and contributors
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Lyranetwork\Payzen\Model\System\Config\Backend\Gift;

use Magento\Framework\App\Filesystem\DirectoryList;

class AddedCards extends \Lyranetwork\Payzen\Model\System\Config\Backend\Serialized\ArraySerialized\ConfigArraySerialized
{
    /**
     * @var \Magento\Framework\File\UploaderFactory
     */
    private $uploaderFactory;

    /**
     * @var \Magento\Framework\Filesystem
     */
    private $filesystem;

    /**
     * @var \Magento\Config\Model\Config\Backend\File\RequestData\RequestDataInterface
     */
    private $requestData;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Lyranetwork\Payzen\Helper\Data $dataHelper
     * @param \Magento\Framework\File\UploaderFactory $uploaderFactory
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Config\Model\Config\Backend\File\RequestData\RequestDataInterface $requestData
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Lyranetwork\Payzen\Helper\Data $dataHelper,
        \Magento\Framework\File\UploaderFactory $uploaderFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Config\Model\Config\Backend\File\RequestData\RequestDataInterface $requestData,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->uploaderFactory = $uploaderFactory;
        $this->filesystem = $filesystem;
        $this->requestData = $requestData;

        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $dataHelper,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Save uploaded files before saving config value
     */
    public function beforeSave()
    {
        $data = $this->getGroups($this->getGroupId()); // get data of gift config group
        $cards = $data['fields'][$this->getField()]['value'];

        if (!is_array($cards) || empty($cards)) {
            $this->setValue([]);
            return parent::beforeSave();
        }

        $i = 0;
        foreach ($cards as $key => $card) {
            $i++;

            if (empty($card)) {
                continue;
            }

            $this->checkCode($card['code'], $i);
            $this->checkName($card['name'], $i);

            $uploadDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA)->getAbsolutePath('payzen/gc/');

            // load latest logo value
            if ($this->dataHelper->fileExists($uploadDir . strtolower($card['code']) . '.png')) {
                $cards[$key]['logo'] = strtolower($card['code']) . '.png';
            }

            // process file upload
            $tmpName = $this->requestData->getTmpName($this->getPath());
            $name = $this->requestData->getName($this->getPath());
            if ($tmpName && isset($tmpName[$key]['logo'])) {
                $file = [];
                $file['tmp_name'] = $tmpName[$key]['logo'];
                $file['name'] = $name[$key]['logo'];

                if ($file['tmp_name'] && $file['name']) { // is there any file uploaded for the current card
                    try {
                        $uploader = $this->uploaderFactory->create($file);
                        $uploader->setAllowedExtensions(['png']);
                        $uploader->setAllowRenameFiles(false);
                        $uploader->setAllowCreateFolders(true);

                        $result = $uploader->save($uploadDir, strtolower($card['code']) . '.png');

                        if (key_exists('file', $result) && !empty($result['file'])) {
                            $cards[$key]['logo'] = $result['file'];
                        }
                    } catch (\Exception $e) {
                        // upload errors
                        $this->throwException('Card logo', $i, $e->getMessage());
                    }
                }
            }
        }
        $this->setValue($cards);

        return parent::beforeSave();
    }

    private function checkCode($value, $i)
    {
        if (empty($value) || !preg_match('#^[A-Za-z0-9\-_]+$#', $value)) {
            $this->throwException('Card code', $i);
        }
    }

    private function checkName($value, $i)
    {
        if (!preg_match('#^[^<>]*$#', $value)) {
            $this->throwException('Card name', $i);
        }
    }
}