<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


class ModelTester extends CI_Controller {

    public function __construct($_mymodels) {
        parent::__construct();
        $this->load->library('session');
        $this->load->helper('form');
        $this->load->helper('url');
        $this->load->helper('html');
        $this->load->database();
        $this->load->library('form_validation');
        //$this->load->library('template');

		$this->mymodels = $_mymodels;//array("adder_model","login_model","recdata_model","admin_model","cs_model","report_model","rec_model");

		foreach ( $this->mymodels as $m ) {
			$this->load->model($m);
		}

		/*
        $this->uid = $this->session->userdata('uid');
        if ( $this->uid != false ) {
            $this->username = $this->session->userdata('username');
		} else $this->username = ""; */
        $this->pdata = array( 'title'=>'');//,"uid"=>$this->uid,"username"=>$this->username);

    }

    public function index() {
        $bod  = "";
        $bod .= "<div>Select a model to manually test</div>";
        $bod .= "<div class='row well'>";
		foreach( $this->mymodels as $m ) {
			$bod .= "<div class='col-md-3 well'>";
			$bod .= "  <div class='lead'><a href='/index.php/".get_class($this)."/model/".$m."'>".$m."</a></div>";
			$bod .= "  <div><a href='/index.php/test/Test_".$m."' class='btn btn-default'>run unit tests</a></div>";
			$bod .= "</div>";
		}
        $bod .= "</div>";
        $this->pdata["title"] = 'Model Tester';
        $this->pdata["body"] = $bod;
        //$this->template->load("default.php",NULL,$this->pdata);
		echo $this->applyTemplate();

    }

    public function model_test() {
        log_message("debug","model_test called with post data:");
        log_message("debug",print_r($_POST,true));
        //$ret = $this->adder_model->$fn();
        // push all params into $pss array
        $pss = array();
        $fail = false;
        $fn= $_POST['fn'];
        $model = $_POST['model'];
        foreach($_POST as $k=>$v) {
            log_message("debug","check POST for param ".$k." ".$v);
            // add params based upon order
            if ( preg_match("/param(\d+)/",$k,$m) ) { 
                $vv = json_decode($v,true);
                log_message("debug","   json as ".print_r($vv,true));
                if ( $vv !== NULL ) $pss[$m[1]] = $vv;
                else //$fail = true;
                    $pss[$m[1]] = $v;
            } 
        }
        log_message("debug","pss".print_r($pss,true));
        if ( $fail ) echo "fail";
        else {
			ob_start();
            $ret = call_user_func_array(array($this->$model,$fn),$pss);
			log_message("debug","ret is ".print_r($ret,true));
			$content = ob_get_clean();
			ob_end_clean();
			log_message("debug","content is ".$content);
			if  ( $ret === FALSE ) {
				log_message("debug","ret failed");
				$ret = array( "msg"     => "ERROR: failed, see log file.");
			} else if ( $content != "" ) {
				log_message("debug","ERROR: there was output from your model: '".$content."'\n");
				$ret = array( "msg"     => "ERROR: there was output in your model.",
					          "content" => $content );
			} else { 
				log_message('debug','json is: "'.json_encode($ret).'"');
			}
			echo json_encode($ret); 
        }
        exit(1);
    }
    public function model($_model)
    {
        $rc = new ReflectionClass($_model);//'Adder_model');
        $methods = $rc->getMethods();
        //print_r($methods);


		// Add navigation
		$mtext   =  "<div class='row well'>".
					"  <a class='btn btn-info btn-xs' href='/index.php/".get_class($this)."'>&lt; back </a>".
					"  <a href='/index.php/test/Test_".$_model."' class='btn btn-default btn-xs'>run unit tests</a>".
					"  <div class='btn-group btn-group-xs' role='group' aria-label='Switch Models'>";
		foreach($this->mymodels as $m ) {
			$btnt = ($m == $_model? "btn-primary" : "btn-info");
			$mtext .= "    <a class='btn ".$btnt."' href='/index.php/".get_class($this)."/model/".$m."'>".$m."</a>";
		}
		$mtext   .= "  </div>".
			        "</div>";

        $mtext .= "<div class='row'>\n";
        $mid = 1;
        foreach ($methods as $m ) {
            if ( ! $m->isConstructor() ) {
                $mtext .= "\n<div class='col-md-6 well'>";
                $mtext .= "<form id='form".$mid."'>";
                $mtext .= "<p><small>".$rc->name."-></small></p>";
                $mtext .= "<fieldset><legend>".$m->name."</legend>";
                //$mtext .= "<div class='lead'>".$m->name."</div>";
                $mtext .= "<input type='hidden' name='fn' value='".$m->name."' ></input>";
                $mtext .= "<input type='hidden' name='model' value='".$_model."' ></input>";
                $dc = $m->getDocComment();
                if ( $dc !== FALSE ) { $mtext .= "<div><pre><code style='font-size: 60%; line-height: 0px;'>".$dc."</code></pre></div>"; }
                //log_message("debug",print_r($m->getDocComment(),true));
                $np=0;
                $mtext .= "<dl class=''>";
                $theurl = "/index.php/".get_class($this)."/model_test/";
                foreach ( $m->getParameters() as $p ) {
                    $mtext .= "<dt>".$p->name."</dt><dd><input name='param".$np."' type='text'></input></dd>";
                    $np++;
                }
                $mtext .= "</dl>";
                $mtext .= "<input name='btn_submit' type='submit' class='btn btn-default' value='Submit' />\n";
                $mtext .= "<div class='hide' id='".$m->name."_output'><pre></pre></div>";
                $mtext .= "<script>$('#form".$mid."').submit(function(_e) { ".
                          "   var data = $(this).serializeArray(); ".
                          "   callModelTest({'url':'".$theurl."','data' : data },'".$m->name."'); _e.preventDefault() });</script>\n";
                $mtext .= "</fieldset>";
                $mtext .= "</form>";
                $mtext .= "</div>\n";

                $mid++;
            }
        }

        $mtext .= "</div>";

        $this->pdata["title"] = $_model;//'Adder Model';
        $this->pdata["body"] = $mtext;
        //$this->pdata["jsfiles"] = array("model_tester.js");
		echo $this->applyTemplate();
        //$this->template->load("default.php",NULL,$this->pdata);

    }

	protected function applyTemplate() {
		$js = 
			"function callModelTest(_d,_fn) {".
			"	var fn = _fn;".
			"	console.log('callModelTest called with url \"'+_d['url']+'\"');".
			"	_d['type'] = 'post';".
			"	$.ajax(_d)".
			"	.error(function(_data) {".
			"		console.log('callModelTest errored '+JSON.stringify(_data,true));".
			"		$('#'+fn+'_output pre').html(_data.responseText); ".
			"		$('#'+fn+'_output').removeClass('hide');".
			"	})".
			"	.done(function(_data) {".
			"		console.log('callModelTest returned '+_data);".
			"		ff = JSON.parse(_data);".
			"		$('#'+fn+'_output pre').html(''+JSON.stringify(ff, null, ' ')+'');".
			"		$('#'+fn+'_output').removeClass('hide');".
			"	});".
			"}";
		$retval= 
			"<!DOCTYPE html>".
			"<html lang='en'>".
			"<head>".
			"	<meta charset='utf-8'>".
			"	<meta name='viewport' content='width=device-width, initial-scale=1' > ".
			"	<title>Model Tester: ".$this->pdata['title']."</title>".
			"	<script type='text/javascript' src='//code.jquery.com/jquery-2.1.3.min.js'></script>".
			"	<link rel='stylesheet' href='//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css' />".
			"	<script type='text/javascript' src='//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js'></script>".
			"   <script type='text/javascript'>".$js."</script>".
			"</head>".
			"<body>".
			"<div class='container'>".
			"	<div class='row'><div class='col-md-12'><h1>Model Tester: ".$this->pdata['title']."</div></h1> </div>".
			"	".$this->pdata['body']."".
			"</body>".
			"</html>";
		return $retval;
	}
}
