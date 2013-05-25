<?php

$path_to_root = '../../';
require_once 'includes/textcart_manager.inc';



function display_error($msg) {
	
}
class TextcartManagerTest extends PHPUnit_Framework_TestCase {
	protected $cart;
	protected $mgr;

	protected  function setUp() {
		$this->cart = array("mock");
		$this->mgr = new TextCartManager();
	}


	public function parseNormalExamples() {
		return array(
							/*** parse quantity ***/
							array("A", "A", null, null, 0)
							,array("A 1 ", "A", '1', null, 0)
							,array("A 17 ", "A", '17', null, 0)
							,array("A +1.7 ", "A", '1.7', null, 0)
							,array("A 1.7 ", "A", null, '1.7', 0)
							,array("A $17 ", "A", null, '17', 0)
							,array("A 2 7.0 ", "A", '2', '7.0', 0)
							,array("A 7.0 2 ", "A", '2', '7.0', 0)
							,array("A $(7.0) 2 ", "A", '2', '7.0', 0)
							,array("A 7.0 +(2 ) ", "A", '2', '7.0', 0)
							,array("A 7.0 +(2.5 ) ", "A", '2.5', '7.0', 0)
							);
	}
/**
 * @group normal
 * @dataProvider parseNormalExamples
 */
    public function testNormal($line, $stock_code, $quantity, $price, $discount=null, $description=null)
    {
			$this->assertParse($line, NORMAL_LINE,  $stock_code, $quantity, $price, $discount, $description, null);
		}

	public function parseDatedExamples() {
		return array(
							/*** Everything is passed ***/
							array("A 10 5.0 3% ^2013/03/01' | hello", "A", '10', '5.0', 0.03, "hello", '2013/03/01')
							,array("A 3% 10 5.0 ^2013/03/01' | hello", "A", '10', '5.0', 0.03, "hello", '2013/03/01')
		);
	}
/**
 * @group dated
 * @dataProvider parseDatedExamples
 */
    public function testDated($line, $stock_code, $quantity, $price, $discount=null, $description=null, $date=null)
    {
			$this->assertParse($line, NORMAL_LINE,  $stock_code, $quantity, $price, $discount, $description, $date);
		}

    public function assertParse($line, $mode,  $stock_code, $quantity, $price, $discount=null, $description=null, $date=null)
    {
			$data = $this->mgr->parse_line($line);
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
