<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

function _cmp_methods($a, $b)
{ if ($a->name == $b->name) { return 0; } return ($a->name < $b->name) ? -1 : 1; }

class CIModelTester extends CI_Controller {

	protected $js_togglediv = "<script>function toggleDiv(_divid) { $('#'+_divid).toggle('fast'); } </script>";

    public function __construct($_mymodels = array(),$_testinglink = null,$_isvalid = true) {
        parent::__construct();

		$this->testinglink = $_testinglink;

		$this->isvalid = false;
		if ( ENVIRONMENT == 'development' || ENVIRONMENT =='testing' ) {
			$this->isvalid = $_isvalid;
			$this->load->library('session');
			$this->load->helper('form');
			$this->load->helper('url');
			$this->load->helper('html');
			$this->load->database();
			$this->load->library('form_validation');

			$this->mymodels = $_mymodels;

			// load all in directory if null
			if ( sizeof($this->mymodels) == 0 ) {
				$this->load->helper('directory');
				$map = directory_map(APPPATH.'models/');

				$this->recursivelyAddModelsFromMap($map);
			}

			//log_message('debug','Loading models : '.print_r($this->mymodels,true));

			foreach ( $this->mymodels as $m ) {
				$this->load->model($m);
			}

			$this->pdata = array( 'title'=>'');
		}

    }

    public function index() {
		if ( $this->isvalid ) {
			$bod  = "";
			$bod .= "<div style='width:90%; margin: 10px;'>A CodeIgniter interactive model tester web interface. Directly call your model functionality for testing and debugging.</div>";
			if ( $this->testinglink != null ) {
				$bod .= "<div style='margin-bottom: 7px;'><a class='btn btn-info' href='".$this->testinglink."'>testing link</a></div>";
			}
			$bod .= "<div class='row well'>";
			foreach( $this->mymodels as $k=>$m ) {
				$mparts = explode("/",$m);
				$mname  = $mparts[sizeof($mparts)-1];
				array_pop($mparts);
				$mpath  = implode("/",$mparts);
				if ( $mpath == "" ) $mpath = "&nbsp;";
				else $mpath .= "/";

				$bod .= "<div class='col-xs-6 col-sm-4 col-md-3 well'>";
				$bod .= "  <div class='' style='margin-bottom: 7px;'><a href='/index.php/".get_class($this)."/model/".$m."'>";
				$bod .= "    <div class='' style='font-size: 120%;'>".$mname."</div>";
				$bod .= "    <div><small>".$mpath."</small></div>";
				$bod .= "  </a></div>";
				//$bod .= "  <div><a href='/index.php/test/Test_".$m."' class='btn btn-default'>run unit tests</a></div>";
				$bod .= "  <div><a href='/index.php/".get_class($this)."/run_unit_tests/".$m."' class='btn btn-info btn-xs' >run unit tests</a></div>";
				$bod .= "</div>";
				$bod .= $this->cClearFix($k,6,4,3,3);
			}
			$bod .= "</div>";
		} else {
			$bod = "<div>Your CIModelTester controller is shutting down because your CodeIgniter project is in a non development state.</div>"; }
		$this->pdata["title"] = 'Main Page';
		$this->pdata["body"] = $bod;
		echo $this->applyTemplate();

    }

    public function model_test() {
		if ( $this->isvalid ) {
			log_message("debug","model_test called with post data:");
			log_message("debug",print_r($_POST,true));
			$pss = array(); // push all params into $pss array
			$fail = false;
			$fn= $_POST['fn'];
			$model = end(explode('/',$_POST['model']));
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
				if ( $content != "" ) {
					log_message("debug","ERROR: there was output from your model: '".$content."'\n");
					$ret = array( "msg"     => "ERROR: there was output in your model.",
								  "content" => $content );
				} else if  ( $ret === FALSE ) {
					log_message("debug","ret failed");
					$ret = "FALSE";
					//$ret = array( "msg"     => "ERROR: function failed, see log file.");
				} else { 
					log_message('debug','json is: "'.json_encode($ret).'"');
				}
				echo json_encode($ret); 
			}
		}
        exit(1);
    }



    public function model($_model)
    {
		$model = implode('/', func_get_args());
		log_message('debug',"model:: ".$model);
		if ( $this->isvalid ) {
			$rc = new ReflectionClass(end(explode('/',$model)));
			//log_message('debug',"COMMENTS: ".$rc->getDocComment());
			$methods = $rc->getMethods();
			$smethods = $methods; usort($smethods, "_cmp_methods");

			// Add navigation
			$mtext   =  "<div class='row well'>".
						"  <a class='btn btn-info btn-xs' href='javascript:history.go(-1)'>&lt; back </a>";
			if ( $this->testinglink != null ) {
						$mtext .= "  <a href='".$this->testinglink."' class='btn btn-info btn-xs'>run tests</a>";
			}
			$mtext .=   "  <a href='/index.php/".get_class($this)."/run_unit_tests/".$model."' class='btn btn-info btn-xs' >run unit tests</a>";
			$mtext .=   "  <div class='btn-group btn-group-xs' role='group' aria-label='Switch Models'>";
			foreach($this->mymodels as $m ) {
				$btnt = ($m == $model? "btn-primary" : "btn-info");
				$mtext .= "    <a class='btn ".$btnt."' href='/index.php/".get_class($this)."/model/".$m."'>".$m."</a>";
			}
			$mtext   .= "  </div>".
						"</div>";

			// Class Comments
			if ( $rc->getDocComment() != "" ) 
				$mtext .= "<div><pre><div class='lead'><strong style='margin-right: 15px;'>Documentation</strong><a class='btn btn-info btn-xs' onclick='toggleDiv(\"docdiv\");'>toggle doc</a></div><div id='docdiv' style='display:none;'>".$rc->getDocComment()."</div></pre></div>";

			// Add listing of methods, with in-page links to call
			$mtext .= "<div class='well'>\n";
			$mtext .= "<div class='lead'><strong>".$model."</strong> method listings</div>\n";

			// public
			$mtext .= "<div class=''><strong>public</strong></div>\n";
			$mtext .= "<div class='row'>\n";
			foreach ($smethods as $m ) {
				if ( ! $m->isConstructor() && $m->isPublic() ) {
					$mtext .= "    <div class='col-xs-6 col-sm-4 col-md-4' style='padding-bottom: 5px;'><a class='' href='#method_".$m->name."'>".$m->name."</a></div>"; } }
			$mtext .= "</div>\n";

			// protected
			$mtext .= "<div class=''><strong>protected</strong></div>\n";
			$mtext .= "<div class='row'>\n";
			foreach ($smethods as $m ) {
				if ( ! $m->isConstructor() && $m->isProtected() ) {
					$mtext .= "    <div class='col-xs-6 col-sm-4 col-md-4' style='padding-bottom: 5px;'><a class='' href='#method_".$m->name."'>".$m->name."</a></div>"; } }
			$mtext .= "</div>\n";

			// all else 
			$mtext .= "<div class=''><strong>all others</strong></div>\n";
			$mtext .= "<div class='row'>\n";
			foreach ($smethods as $m ) {
				if ( ! $m->isConstructor() && !($m->isProtected() || $m->isPublic()) ) {
					$mtext .= "    <div class='col-xs-6 col-sm-4 col-md-4' style='padding-bottom: 5px;'><a class='' href='#method_".$m->name."'>".$m->name."</a></div>"; } }
			$mtext .= "</div>\n";

			$mtext .= "</div>\n";

			// Add functionality to call each method
			$mtext .= "<div class='row'>\n";
			$mid = 1;
			$k = 0;
			foreach ($methods as $m ) {
				if ( ! $m->isConstructor() ) {
					$mtext .= "\n<div class='col-xs-6 col-sm-4 col-md-4 well'>";
					$mtext .= "<div id='method_".$m->name."'><a name='method_".$m->name."'></a></div>";
					$mtext .= "<form id='form".$mid."'>";
					$mtext .= "<p><small>".$rc->name."-></small></p>";
					$mtext .= "<fieldset><legend>".$m->name."</legend>";
					//$mtext .= "<div class='lead'>".$m->name."</div>";
					$mtext .= "<input type='hidden' name='fn' value='".$m->name."' ></input>";
					$mtext .= "<input type='hidden' name='model' value='".$model."' ></input>";
					$dc = $m->getDocComment();
					if ( $dc !== FALSE ) { $mtext .= "<div style='margin-bottom: 7px;'><a class='btn btn-xs btn-info' onclick='toggleDiv(\"doc_method_".$m->name."\"); return false;'>toggle doc</a><pre id='doc_method_".$m->name."' style='display: none;'><code style='font-size: 60%; line-height: 0;'>".join("\n", array_map("ltrim", explode("\n", $dc)))."</code></pre></div>"; }
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
					$mtext .= "<a class='btn btn-default btn-sm' href='javascript:toggleDiv(\"".$m->name."_output\")'>toggle</a>\n";
					$mtext .= "<div class='hide' id='".$m->name."_output'><pre></pre></div>";
					$mtext .= "<script>$('#form".$mid."').submit(function(_e) { ".
							  "   var data = $(this).serializeArray(); ".
							  "   callModelTest({'url':'".$theurl."','data' : data },'".$m->name."'); _e.preventDefault() });</script>\n";
					$mtext .= "</fieldset>";
					$mtext .= "</form>";
					$mtext .= "</div>\n";
					$mtext .= $this->cClearFix($k,6,4,4,4);

					$mid++;
					$k= $k+1;
				}
			}

			$mtext .= "</div>\n";
			$mtext .= $this->js_togglediv;


		} else {
			$bod = "<div>Your CIModelTester controller is shutting down because your CodeIgniter project is in a non development state.</div>"; }

		$this->pdata["title"] = "Model ".$model;//'Adder Model';
		$this->pdata["body"] = $mtext;
		echo $this->applyTemplate();
    }

	// Call the test method on the model
	// srcmodel = foo/bar_model              <-file
	// testmodel = foo/tests/test_bar_model  <-file
	// varmodel = bar_model        <-CI name ex. $this->bar_model
	// tvarmodel = test_bar_model  <-CI name ex. $this->test_bar_model
	public function run_unit_tests($_model) {
		$args = func_get_args();
		$srcmodel = implode('/', $args); 
		$varmodel = $args[sizeof($args)-1]; // the name of the model in $this->
		$tvarmodel = "test_$varmodel";

		// create test model name by inserting "tests/test_" before the model name
		$testmodel = $args;
		$testmodel[sizeof($testmodel)-1] = "tests/test_$varmodel";
		$testmodel = implode('/', $testmodel); // turn array to string
		//log_message('debug',"testmodel is '$testmodel' and srcmodel is '$srcmodel' and model name is $varmodel");

		$bod = "";
		$bod .= "<a class='btn btn-info btn-xs' href='javascript:history.go(-1)'>&lt; back </a>";
		if ( ENVIRONMENT != "testing" ) {
			$bod .= "<div class='lead'>Not in testing environment. Currently set to ".ENVIRONMENT.".</div>";
		} else if( ! file_exists(APPPATH."models/$testmodel.php") ){ //file_exists(APPPATH."models/tests/test_$model.php") 
			$bod .= "<div class='lead'>No testing model. create a model in '".APPPATH."models/$testmodel.php'</div>";
			$bod .= "<h3>Example Code:</h3>";
			$bod .= "<pre><code>".
"&lt;?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');\n".
"\n".
"class ".$testmodel." extends ".$srcmodel."\n".
"{\n".
"    function __construct() { parent::__construct(); }\n".
"\n".
"    // Generic Model tests\n".
"    public function test() {\n".
"        \$this->unit->run(1,1,'Is One One');\n".
"        return \$retval;\n".
"    }\n".
"\n".
"    // Tests specific to a method\n".
"    public function test_[YOUR METHOD]() {\n".
"        \$this->unit->run(1,1,'Is One One');\n".
"        return \$retval;\n".
"    }\n".
"};\n".
"</pre></code>";
		} else {
			$this->load->library("unit_test");
			$rp = $this->load->model($testmodel);

			// generic test
			$bod .= "<h3>Generic Test</h3>\n";
			$rr = $this->{$tvarmodel}->test(); //$rr = $this->{$testmodel}->test();
			$bod .= "<div class='container thumbnail'><div>".$this->unit->report()."</div><div>$rr</div></div>";
			$this->unit->results = array();

			$rc = new ReflectionClass(end(explode('/',$testmodel)));
			$methods = $rc->getMethods();
			$smethods = $methods; usort($smethods, "_cmp_methods");

			// find methods, and look for their tests
			$testable = 0;
			$tested   = 0;
			$tested_failed = 0;
			foreach( $smethods as $m) {
				if ( ! $m->isConstructor() ) {
					if( !strpos($m->name,"test_",0) && $m->name != "test" && $m->name != "__get"  ) {
						$testable++;
						$fnd = false;
						foreach( $smethods as $mt) {

							if( $mt->name == "test_".$m->name ) {
								$tested++;
								//$bod.= "<div> found test for ".$m->name."</div>";
								$ret = $this->{$tvarmodel}->{$mt->name}();
								$r_pass = 0; $r_tot = 0; foreach ( $this->unit->result() as $rs ) { if ( $rs['Result'] == 'Passed') {$r_pass++;} $r_tot++; }
								if ( $r_pass != $r_tot ) $tested_failed++;
								$bod .= "<div class='container'>\n";
								$bod .= "  <div class='row'>\n";
								$bod .= "    <div class='thumbnail'>\n";
								$bod .= "      <h4><a href='/index.php/MyModelTester/model/".$srcmodel."#method_".$m->name."' alt='link to method'>".$m->name."</a> Tested</h4>\n";
								//$bod .= "      <div><pre><code>".print_r($this->unit->result(),true)."</code></pre></div>\n";
								$bod .= "      <div>result: [".$r_pass."/".$r_tot."] ".($r_pass==$r_tot ? 'passed':'failed')." <a onclick='toggleDiv(\"".$mt->name."\"); return false;' class='btn btn-info btn-xs'>results</a></div>";
								$bod .= "      <div id='".$mt->name."' style='".($r_pass==$r_tot ? 'display: none':'')."'>";
								$bod .= "        <div>".$this->unit->report()."</div>\n";
								$bod .= "        <div><h5>returned:</h5>".$ret."</div>\n";
								$bod .= "      </div>\n";
								$bod .= "    </div>\n";
								$bod .= "  </div>\n";
								$bod .= "</div>\n";
								$this->unit->results = array(); // reset it
								$fnd = true;
								break;
							}
						}
						//if ( $fnd == false ) $bod.= "<div> no test for ".$m->name."</div>";
					}
				}
			}

			$bod .= "<div class='well'>Tested ".$tested." of ".$testable." methods with $tested_failed failures</div>";
			$bod .= $this->js_togglediv;
			$this->{$tvarmodel}->onExit();
		}

		$this->pdata["title"] = 'Unit Test : '.$srcmodel;
		$this->pdata["body"] = $bod;
		echo $this->applyTemplate();
	}



	protected function applyTemplate() {
		$js = 
			"function callModelTest(_d,_fn) {".
			"	var fn = _fn;".
			"	console.log('callModelTest called with url \"'+_d['url']+'\"');".
			"	$('#'+fn+'_output pre').html('<div>calling</div>');".
			"	$('#'+fn+'_output').removeClass('hide');".
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
			"		$('#'+fn+'_output').show('fast');".
			"	});".
			"}".
			"function toggleResult(_id) {\n".
			"  $(_id).toggle();\n".
			"}\n";

		$retval= 
			"<!DOCTYPE html>".
			"<html lang='en'>".
			"<head>".
			"	<meta charset='utf-8'>".
			"	<meta name='viewport' content='width=device-width, initial-scale=1' > ".
			"	<title>CIModelTester: ".$this->pdata['title']."</title>".
			"	<script type='text/javascript' src='//code.jquery.com/jquery-2.1.3.min.js'></script>".
			"	<link rel='stylesheet' href='//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css' />".
			"	<script type='text/javascript' src='//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js'></script>".
			"   <script type='text/javascript'>\n".$js."\n</script>".
			"</head>".
			"<body>".
			"<div class='container'>".
			"	<div class='row'><div class='col-md-12'><h1>CIModelTester: ".$this->pdata['title']."</h1></div></div>".
			"	".$this->pdata['body']."".
			"   <div class='row well'><div class='col-md-12'><small>A <a href='http://conquestcreations.com'>Conquest Creations</a> product. Find the <a href='https://github.com/cwingrav/CIModelTester'>Github repo for CIModelTester here.</a> </small></div></div>".
			"</body>".
			"</html>";
		return $retval;
	}

	protected function recursivelyAddModelsFromMap($_map,$_path = "") {
		//log_message('debug',"map ($_path): ".print_r($_map,true));
		foreach ( $_map as $k=>$m ) {

			//log_message('debug',"Checking ($k) (".($k==='tests').") (".print_r($m,true).") ");
			if ( $k === 'tests' ) { log_message('debug','   ...skip'); } // skip
			else if ( is_array($m) ) { log_message('debug',"  ...recurse on $k");  $this->recursivelyAddModelsFromMap($m,$_path.$k."/"); }
			else {
				$v = strrpos($m,"_model.php",-10);
				//log_message('debug',"  checking ($v)");
				if ( $v !== false ) {
					//log_message('debug',"   - Found $_path$m");
					array_push($this->mymodels,rtrim($_path.$m,".php"));
				}
			}
		}
	}

	protected function cClearFix($_ccount,$_xs,$_sm,$_md,$_lg) {
		$cf="";
		if ( $_ccount != 0 ) {
			if ( ($_xs != 0) && ((($_ccount+1) % (12/$_xs)) == 0) ) { $cf .= " visible-xs-block"; }
			if ( ($_sm != 0) && ((($_ccount+1) % (12/$_sm)) == 0) ) { $cf .= " visible-sm-block"; }
			if ( ($_md != 0) && ((($_ccount+1) % (12/$_md)) == 0) ) { $cf .= " visible-md-block"; }
			if ( ($_lg != 0) && ((($_ccount+1) % (12/$_lg)) == 0) ) { $cf .= " visible-lg-block"; }
		}

		$retval = "";
		if ( $cf != "" ) { $retval = "<div class='clearfix".$cf."'></div>"; }

		return $retval;
	}
}
