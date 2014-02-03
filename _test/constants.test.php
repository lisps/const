<?php
class plugin_const_constants_test extends DokuWikiTest {

    public function setup() {
        $this->pluginsEnabled[] = 'const';
        parent::setup();
    }


    public function test_page_user_constants() {
        
        saveWikiText('test:plugin_const:pageconstants', 
            '<const>'.DOKU_LF
            .'ID=%ID%'.DOKU_LF
            .'namespace=%NAMESPACE%'.DOKU_LF
            .'User=%USER%'.DOKU_LF
            .'</const>'.DOKU_LF
            .'ID:%%ID%%'.DOKU_LF
            .'NAMESPACE:%%namespace%%'.DOKU_LF
            .'User:%%User%%'.DOKU_LF,
            'setup for test');

        
        $request = new TestRequest();
        $request->get(array('id' => 'test:plugin_const:pageconstants'), '/doku.php');
        $HTML = $request->execute();
        $HTML = $HTML->queryHTML('.page p');

        $this->assertTrue(strpos($HTML, 'ID:pageconstants') !== false, 'Page ID is pageconstants');
        $this->assertTrue(strpos($HTML, 'NAMESPACE:test:plugin_const') !== false, 'Namespace is test:plugin_const');
        $this->assertTrue(strpos($HTML, 'User:') !== false, 'anonymous');
    }
    
    public function test_date_constants() {
        
        saveWikiText('test:plugin_const:dateconstants', 
            '<const>'.DOKU_LF
            .'YEAR=%YEAR%'.DOKU_LF
            .'MONTH=%MONTH%'.DOKU_LF
            .'MONTHNAME=%MONTHNAME%'.DOKU_LF
            .'WEEK=%WEEK%'.DOKU_LF
            .'DAY=%DAY%'.DOKU_LF
            .'DAYNAME=%DAYNAME%'.DOKU_LF
            .'</const>'.DOKU_LF
            
            .'YEAR:%%YEAR%%'.DOKU_LF
            .'MONTH:%%MONTH%%'.DOKU_LF
            .'MONTHNAME:%%MONTHNAME%%'.DOKU_LF
            .'WEEK:%%WEEK%%'.DOKU_LF
            .'DAY:%%DAY%%'.DOKU_LF
            .'DAYNAME:%%DAYNAME%%'.DOKU_LF,

            'setup for test');
        
        $request = new TestRequest();
        $request->get(array('id' => 'test:plugin_const:dateconstants'), '/doku.php');
        $HTML = $request->execute();
        $HTML = $HTML->queryHTML('.page p');
        //echo $HTML;
        $this->assertTrue(strpos($HTML, 'YEAR:'.date('Y')) !== false);
        $this->assertTrue(strpos($HTML, 'MONTH:'.date('m')) !== false);
        $this->assertTrue(strpos($HTML, 'MONTHNAME:'.date('F')) !== false);
        $this->assertTrue(strpos($HTML, 'WEEK:'.date('W')) !== false);
        $this->assertTrue(strpos($HTML, 'DAY:'.date('d')) !== false);
        $this->assertTrue(strpos($HTML, 'DAYNAME:'.date('l')) !== false);
    }
}
