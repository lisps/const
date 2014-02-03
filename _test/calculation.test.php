<?php

class plugin_const_calculation_test extends DokuWikiTest {

    public function setup() {
        $this->pluginsEnabled[] = 'const';
        parent::setup();
    }

    
    public function test_math() {
        saveWikiText('test:plugin_const:math', 
            '<const>'.DOKU_LF
            .'value1=4'.DOKU_LF
            .'formular=value1 * 10 +2'.DOKU_LF
            .'result:formular'.DOKU_LF
            .'</const>'.DOKU_LF
            .'%%result%%'.DOKU_LF,
            'setup for test');
        $HTML = p_wiki_xhtml('test:plugin_const:math');
        $this->assertTrue(strpos($HTML, '42') !== false, 'Calculation is 42');
    }
}
