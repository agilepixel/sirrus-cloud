<?php
/**
 * Class BasicTest
 *
 * @package Testplugin
 */

/**
 * Sample test case.
 */
class BasicTest extends WP_Ajax_UnitTestCase {

	/**
	 * A single example test.
	 */
	public function test_sample() {

		$upload_dir = plugin_dir_path(__FILE__).'../testimport/';
		$files = scandir($upload_dir);
        foreach ($files as $file) {
            if (preg_match('/^import-([0-9]+)\.json$/', $file, $matches)) {
                $_GET['debug'] = $matches[1];
                try {
                    $this->_handleAjax('aimp');
                } catch (\WPAjaxDieContinueException $e) {
					echo $file . "\n";
                    echo $this->_last_response . "\n";
				}
				
                $this->assertTrue(true);
            }
        }
	}
}
