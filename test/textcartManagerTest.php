<?php

$path_to_root = '../../';
require_once 'includes/textcart_manager.inc';


class TextcartManagerTest extends PHPUnit_Framework_TestCase {
	protected $cart;
	protected $mgr;

	protected  function setUp() {
		$this->cart = array("mock");
		$this->mgr = new TextCartManager();
	}


	public function parseExamples() {
		return array(
							array("A 10 5.0 3% | hello", NORMAL_LINE, "A", '10', '5.0', 0.03, "hello")
							,array("A", "A", 1, 0.0, 0, "")
		);
	}
/**
 *      * @dataProvider parseExamples
 *      */
    public function testAdd($line, $mode,  $stock_code, $quantity, $price, $discount=null, $description=null, $date=null)
    {
			$data = $this->mgr->parse_line($line);
			$this->assertEquals($data, array(
      "mode" => $mode
      ,"stock_code" => $stock_code
      ,"quantity" => $quantity
      ,"price" => $price
      ,"discount" => $discount
      ,"description" => $description
      ,"date" => $date
				)
			);
    }
 
} 
