<?php
/* +-----------------------------------------------------------------------------+
*    OpenEMR - Open Source Electronic Medical Record
*    Copyright (C) 2014 Z&H Consultancy Services Private Limited <sam@zhservices.com>
*
*    This program is free software: you can redistribute it and/or modify
*    it under the terms of the GNU Affero General Public License as
*    published by the Free Software Foundation, either version 3 of the
*    License, or (at your option) any later version.
*
*    This program is distributed in the hope that it will be useful,
*    but WITHOUT ANY WARRANTY; without even the implied warranty of
*    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*    GNU Affero General Public License for more details.
*
*    You should have received a copy of the GNU Affero General Public License
*    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
*    @author  Riju KP <rijukp@zhservices.com>
* +------------------------------------------------------------------------------+
*/
namespace Carecoordination\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Application\Listener\Listener;
use Documents\Controller\DocumentsController;
use Carecoordination\Model\CcdTable;
use Carecoordination\Model\CarecoordinationTable;
use Documents\Model\DocumentsTable;
use C_Document;
use Document;
use CouchDB;
use xmltoarray_parser_htmlfix;

class CcdController extends AbstractActionController
{
    /**
     * @var \Carecoordination\Model\CcdTable
     */
    protected $ccdTable;
    
    protected $carecoordinationTable;
    
    protected $documentsTable;

    /**
     * @var Documents\Controller\DocumentsController
     */
    private $documentsController;
    
    public function __construct(
        CcdTable $ccdTable,
        CarecoordinationTable $carecoordinationTable,
        DocumentsTable $documentsTable,
        DocumentsController $documentsController
    ) {
    
        $this->listenerObject = new Listener;
        $this->ccdTable = $ccdTable;
        $this->carecoordinationTable = $carecoordinationTable;
        $this->documentsTable = $documentsTable;
        $this->documentsController = $documentsController;
    }

    /*
    * Upload CCD file
    */
    public function uploadAction()
    {
        $request          = $this->getRequest();
        $upload           = $request->getPost('upload');
        $category_details = $this->getCarecoordinationTable()->fetch_cat_id('CCD');

        if ($upload == 1) {
            $time_start         = date('Y-m-d H:i:s');
            $obj_doc            = $this->documentsController;
            $cdoc               = $obj_doc->uploadAction($request);
            $uploaded_documents = array();
            $uploaded_documents = $this->getCarecoordinationTable()->fetch_uploaded_documents(array('user' => $_SESSION['authId'], 'time_start' => $time_start, 'time_end' => date('Y-m-d H:i:s')));
            if ($uploaded_documents[0]['id'] > 0) {
                $_REQUEST["document_id"]    = $uploaded_documents[0]['id'];
                $_REQUEST["batch_import"]   = 'YES';
                $this->importAction();
            }
        } else {
            $result = \Documents\Plugin\Documents::fetchXmlDocuments();
            foreach ($result as $row) {
                if ($row['doc_type'] == 'CCD') {
                    $_REQUEST["document_id"] = $row['doc_id'];
                    $this->importAction();
                    $this->updateDocumentCategoryUsingCatname($row['doc_type'], $row['doc_id']);
                }
            }
        }

        $records = $this->getCarecoordinationTable()->document_fetch(array('cat_title' => 'CCD','type' => '13'));
        $view = new ViewModel(array(
          'records'       => $records,
          'category_id'   => $category_details[0]['id'],
          'file_location' => basename($_FILES['file']['name']),
          'patient_id'    => '00',
          'listenerObject'=> $this->listenerObject
        ));
        return $view;
    }

    /*
    * Function to import the data CCD file to audit tables.
    *
    * @param    document_id     integer value
    * @return   none
    */
    public function importAction()
    {
        $request     = $this->getRequest();
        if ($request->getQuery('document_id')) {
            $_REQUEST["document_id"] = $request->getQuery('document_id');
            $category_details          = $this->getCarecoordinationTable()->fetch_cat_id('CCD');
            $this->getDocumentsTable()->updateDocumentCategory($category_details[0]['id'], $_REQUEST["document_id"]);
        }

        $document_id                      =    $_REQUEST["document_id"];
        $xml_content                      =    $this->getCarecoordinationTable()->getDocument($document_id);

        $xmltoarray                       =    new \Zend\Config\Reader\Xml();
        $array                            =    $xmltoarray->fromString((string) $xml_content);

        $this->getCcdTable()->import($array, $document_id);

        // we return just empty Json, otherwise it triggers an error if we don't return some kind of HTTP response.
        $view = new \Zend\View\Model\JsonModel();
        $view->setTerminal(true);
        return $view;
    }
    /**
    * Table gateway
    * @return \Carecoordination\Model\CcdTable
    */
    public function getCcdTable()
    {
        return $this->ccdTable;
    }
    /**
     * Table gateway
     * @return object
     */
    public function getCarecoordinationTable()
    {
        return $this->carecoordinationTable;
    }
    
    public function getDocumentsTable()
    {
        return $this->documentsTable;
    }
}
