<?php
/**
 * @group plugin_const
 * @group plugins
 */
class plugin_const_include_support_test extends DokuWikiTest {

    public function setup() {
        $this->pluginsEnabled[] = 'const';
        $this->pluginsEnabled[] = 'include';
        $this->_createPages();
        parent::setup();
    }

    public function test_basic_sectionfix() {
        $request = new TestRequest();
        $response = $request->get(array('id' => 'test:plugin_const:start'), '/doku.php');

        $first_sec = $response->queryHTML('form.btn_secedit input[name="range"]')->eq(0)->attr('value');
        $second_sec = $response->queryHTML('form.btn_secedit input[name="range"]')->eq(1)->attr('value');
        $third_sec = $response->queryHTML('form.btn_secedit input[name="range"]')->eq(2)->attr('value');

        $this->assertEquals('57-87',  $first_sec);
        $this->assertEquals('88-118', $second_sec);
        $this->assertEquals('119-',   $third_sec);
    }
    
    public function test_include_sectionfix() {
        $request = new TestRequest();
        $response = $request->get(array('id' => 'test:plugin_const:include'), '/doku.php');

        $section = array();

        $section[] = $response->queryHTML('form.btn_secedit input[name="range"]')->eq(0)->attr('value');
        $section[] = $response->queryHTML('form.btn_secedit input[name="range"]')->eq(1)->attr('value');
        $section[] = $response->queryHTML('form.btn_secedit input[name="range"]')->eq(2)->attr('value');
        $section[] = $response->queryHTML('form.btn_secedit input[name="range"]')->eq(3)->attr('value');
        $section[] = $response->queryHTML('form.btn_secedit input[name="range"]')->eq(4)->attr('value');
        $section[] = $response->queryHTML('form.btn_secedit input[name="range"]')->eq(5)->attr('value');
        
        $this->assertEquals('71-101',  $section[0]);
        $this->assertEquals('57-87',   $section[1]);
        $this->assertEquals('88-118',  $section[2]);
        $this->assertEquals('119-',    $section[3]);
        $this->assertEquals('102-141', $section[4]);
        $this->assertEquals('142-',    $section[5]);
    }
    
    private function _createPages() {
        saveWikiText('test:plugin_const:include', 
            '<const>'.DOKU_LF
            .'var1=test:plugin_const:start'.DOKU_LF
            .'var2=123456789123456789'.DOKU_LF
            .'</const>'.DOKU_LF
            .'====== Header1 ======'.DOKU_LF
            .'%%var2%%'.DOKU_LF
            .'====== Header2 ======'.DOKU_LF
            .'{{page>%%var1%%}}'.DOKU_LF
            .'====== Header3 ======'.DOKU_LF,
            'setup for test');
        saveWikiText('test:plugin_const:start', 
            '<const>'.DOKU_LF
            .'var1=123456789'.DOKU_LF
            .'var2=123456789123456789'.DOKU_LF
            .'</const>'.DOKU_LF
            .'====== Header1 ======'.DOKU_LF
            .'%%var2%%'.DOKU_LF
            .'====== Header2 ======'.DOKU_LF
            .'%%var2%%'.DOKU_LF
            .'====== Header3 ======'.DOKU_LF,
            'setup for test');
    }

}
