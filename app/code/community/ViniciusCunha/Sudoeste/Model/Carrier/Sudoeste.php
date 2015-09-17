<?php
/**
 * Vinicius Augusto Cunha
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL).
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Shipping (Frete)
 * @package    ViniciusCunha_Sudoeste
 * @copyright  Copyright (c) 2015 Vinicius Cunha
 * @author     Vinicius Augusto Cunha <viniciusaugustocunha@gmail.com>
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

require_once(Mage::getBaseDir('lib') . '/nusoap/lib/nusoap.php');

class ViniciusCunha_Sudoeste_Model_Carrier_Sudoeste     
		extends Mage_Shipping_Model_Carrier_Abstract
		implements Mage_Shipping_Model_Carrier_Interface
	{  
        protected $_code            = 'sudoeste';
        protected $_fromZip         = null;
        protected $_toZip           = null;
        
        /** 
        * Collect rates for this shipping method based on information in $request 
        * 
        * @param Mage_Shipping_Model_Rate_Request $data 
        * @return Mage_Shipping_Model_Rate_Result 
        */  
        public function collectRates(Mage_Shipping_Model_Rate_Request $request){  
            $result = Mage::getModel('shipping/rate_result');
            
            $this->_fromZip = Mage::getStoreConfig('shipping/origin/postcode', $this->getStore());
            $this->_toZip = $request->getDestPostcode();            
            
            $consultaValor = $this->getValorSudoeste(); 

            //echo "<pre>";
            //print_r($consultaValor);
            //exit();

            if($consultaValor['erro'] == "-1"){
                Mage::getSingleton('core/session')->addError("Desculpe, mais não estamos realizando entregas nessa localização");
            }else{
                $valorSudoeste = $consultaValor['totalFrete'];
             
                if ($valorSudoeste > 0) {
                    $method = Mage::getModel('shipping/rate_result_method');
                    $method->setCarrier($this->_code);
                    $method->setCarrierTitle($this->getConfigData('title'));
                    $method->setMethod($this->_code);
                    $method->setMethodTitle($this->getConfigData('name') . " - Entrega será realizada em até ".$consultaValor['prazo']." dias uteis");
                    $method->setPrice($valorSudoeste);
                    $method->setCost($valorSudoeste);
                    $result->append($method);
                }
            }        
            
            return $result;
        }
                
        /**
         * Envia Dados para o Webservice da Sudoeste Transportes
         * http://ssw.inf.br/ws/sswCotacao/
         */
        protected function getValorSudoeste() {


            $client = new nusoap_client('http://www.ssw.inf.br/ws/sswCotacao/index.php?wsdl', 'wsdl');

            $call = 'cotacao';

            $result = $client->call($call, array('dominio' => "SUD",
                                                 'login' => $this->getConfigData('login'),
                                                 'senha' => $this->getConfigData('senha'),
                                                 'cnpjPagador' => $this->getConfigData('cnpj'),
                                                 'cepOrigem' => $this->getConfigData('cep_origem'),
                                                 'cepDestino' => $this->formatarCep($this->_toZip),
                                                 'valorNF' => number_format($this->getValor(),2,',',''),
                                                 'quantidade' => $this->qtdItens(),
                                                 'peso' => number_format($this->getPeso(),2,',',''),
                                                 'volume' =>  $this->qtdItens(),
                                                 'mercadoria' => $this->qtdItens()));

            if ($client->fault) {
                return "Erro ao conectar no Webservice";
            } else {
                $err = $client->getError();
                if ($err) {
                   //caso tenha erro retorna o erro
                   return $err;
                } else {
                    //caso tenha dados, retorna em array o resultado do calculo
                    return $this->convertXml($result);
                }
            }

             Mage::log($err, Zend_Log::INFO, 'sudoeste.log');
             Mage::log($result, Zend_Log::INFO, 'sudoeste.log');
        }
        
        /**
         * Retorna peso dos produtos
         * 
         * @return float
         */
        protected function getPeso(){
        	$itens = Mage::getSingleton('checkout/session')->getQuote()->getAllVisibleItems();
        	$total = 0.0;
        	
        	foreach($itens as $item) {
        		$_product = $item->getProduct();        	
        		$total += (float) $_product->getWeight() * $item->getQty();
        	}
        	
        	return $total;
        }
        
        /**
         * Retorna peso cubado dos produtos
         *
         * @return float
         */
        protected function getPesoCubado(){
        	$itens = Mage::getSingleton('checkout/session')->getQuote()->getAllVisibleItems();
        	$total = 0.0;
        	 
        	foreach($itens as $item) {
        		$_product = $item->getProduct();
        		$total += ((float) $item->getQty()) * ((float) $_product->getVolumeLargura()/100) * ((float) $_product->getVolumeAltura()/100) * ((float) $_product->getVolumeComprimento()/100);
        	}
        	 
        	return $total;
        }
        
        
        /**
         * Retorna valor dos produtos
         * 
         * @return float
         */
        protected function getValor() {
            $totals = Mage::getSingleton('checkout/cart')->getQuote()->getTotals();
            $subtotal = $totals["subtotal"]->getValue();
            return (float) $subtotal;
        }

		/**
		 * Get allowed shipping methods
		 *
		 * @return array
		 */
		public function getAllowedMethods() {
			return array($this->_code=>$this->getConfigData('name'));
		}

        /**
         * pega a quantidade de itens do carrinho
         *
         * @return int
         */
        public function qtdItens(){
           return (int) Mage::getModel('checkout/cart')->getQuote()->getItemsQty();
        }
        /**
         * Converte o xml em array
         *
         * @return array
         */
        public function convertXml($result){

            $xml = simplexml_load_string($result);
            $json = json_encode($xml);
            $array = json_decode($json,TRUE);
            return $array;
        }
		

        public function formatarCep($cep){
            return str_replace("-", "", $cep);
        }
    }  
