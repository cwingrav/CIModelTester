<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

function _cmp_methods($a, $b)
{ if ($a->name == $b->name) { return 0; } return ($a->name < $b->name) ? -1 : 1; }

function pack_ret($_ret) { return "<div><pre style='font-size: 8px;'><code>".print_r($_ret,true)."</code></pre></div>"; }


class CIModelTester extends CI_Controller {


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
			$this->mytestmodels = array();

			// load all in directory if null
			if ( sizeof($this->mymodels) == 0 ) {
				$this->load->helper('directory');
				$map = directory_map(APPPATH.'models/');

				$this->recursivelyAddModelsFromMap($map);
			}
			$this->recursivelyAddTestModelsFromMap($map,false);

			//log_message('debug','Loading models : '.print_r($this->mymodels,true));
			//log_message('debug','Loading test models : '.print_r($this->mytestmodels,true));

			foreach ( $this->mymodels as $m ) {
				$this->load->model($m);
			}

			$this->pdata = array( 'title'=>'');
		}

    }



	// --------------------------------------------------------------------- 
	// index()
	// --------------------------------------------------------------------- 
	// Display models to interact with
	// --------------------------------------------------------------------- 
    public function index() {
		if ( $this->isvalid ) {
			$bod  = "<div class='container'><div class='row'><h1>CIModelTester</h1></div></div>";
			$bod .= "<div class='container'><div class='row'><div class='lead'>A CodeIgniter interactive model tester web interface. Directly call your model functionality for testing and debugging.</div></div></div>";
			if ( $this->testinglink != null ) {
				$bod .= "<div style='margin-bottom: 7px;'><a class='btn btn-info' href='".$this->testinglink."'>testing link</a></div>";
			}
			$bod .= "<div class='container'>";
			$bod .= $this->applySwitchModelTemplate("model");
			$bod .= "</div>";
		} else {
			$bod = "<div>Your CIModelTester controller is shutting down because your CodeIgniter project is in a non development state.</div>"; }
		$this->pdata["title"] = "";
		$this->pdata["body"] = $bod;
		echo $this->applyTemplate();

    }


	// --------------------------------------------------------------------- 
	// model_test()
	// --------------------------------------------------------------------- 
	// The magic. This function is called via Ajax to run the model with the 
	// specified parameters. Parameters that are arrays can be sent. 
	//    ex. parm0=>"[1,2]" sends array(1,2) as the 1st parameter 
	// It returns the results in JSON.
	// 
	//   $_POST is used:
	//      model -> model to call
	//      fn -> method name
	//      paramN -> values to pass to that method, with N being a number 
	//                starting at 0 and incrementing. 
	//                ex. param0=>1st param, param1=>2nd param, etc.
	// --------------------------------------------------------------------- 
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



	// --------------------------------------------------------------------- 
	// model($_model) 
	// --------------------------------------------------------------------- 
	// The working screen. This loads the methods of the model and let's you 
	// use them. $_model is an unused variable since this uses variable 
	// arguments in true CodeIgniter fashion.
	//
	//   ex. http://<project>/index.php/CIModelTester/model/dir1/mymodel_model#method_getData
	//    This uses the model in APPPATH."/models/dir1/mymodel_model.php". 
	//    This also scroll to the method "getData" on the generated webpage.
	// --------------------------------------------------------------------- 
    public function model($_model)
    {
		$bod = "";
		$model = implode('/', func_get_args());
		if ( $this->isvalid ) {
			$rc = new ReflectionClass(end(explode('/',$model)));

			// Grab the methods on the class and alpha sort them
			$methods = $rc->getMethods();
			$smethods = $methods; usort($smethods, "_cmp_methods");

			$bod .= $this->applyNavigationTemplate($model,'model');


				/*
			$mtext .=   "  <div class='btn-group btn-group-xs' role='group' aria-label='Switch Models'>";
			foreach($this->mymodels as $m ) {
				$btnt = ($m == $model? "btn-primary" : "btn-info");
				$mtext .= "    <a class='btn ".$btnt."' href='/index.php/".get_class($this)."/model/".$m."'>".$m."</a>";
			}
			$mtext   .= "  </div>".
						"</div>";
				 */
			$mtext = $bod;

			// Class Comments
			if ( $rc->getDocComment() != "" )  {
				$mtext .= "<div class='container'>";
				$mtext .= "  <div class='row'><pre><div class='lead'><strong style='margin-right: 15px;'>Documentation</strong><a class='btn btn-info btn-xs' onclick='toggleDiv(\"docdiv\");'>toggle doc</a></div><div id='docdiv' style='display:none;'>".$rc->getDocComment()."</div></pre></div>";
				$mtext .= "</div>";
			}

			// Add listing of methods, with in-page links to call
			$mtext .= "<div class='container well'>";
			$mtext .= "  <div class=''>\n";
			$mtext .= "    <div class='lead'><strong>".$model."</strong> method listings</div>\n";
			$mtext .= "  </div>\n";

			// public
			$mtext .= "  <div class='row'><strong>public</strong></div>\n";
			$mtext .= "  <div class='row'>\n";
			foreach ($smethods as $m ) {
				if ( (!$m->isConstructor() ) && ($m->name != "__get" ) &&  $m->isPublic() ) {
					$mtext .= "    <div class='col-xs-6 col-sm-4 col-md-4' style='padding-bottom: 5px;'><a class='' href='#method_".$m->name."'>".$m->name."</a></div>"; } }
			$mtext .= "  </div>\n";

			// protected
			$mtext .= "  <div class='row'><strong>protected</strong></div>\n";
			$mtext .= "  <div class='row'>\n";
			foreach ($smethods as $m ) {
				if ( ! $m->isConstructor() && $m->isProtected() ) {
					$mtext .= "    <div class='col-xs-6 col-sm-4 col-md-4' style='padding-bottom: 5px;'><a class='' href='#method_".$m->name."'>".$m->name."</a></div>"; } }
			$mtext .= "  </div>\n";

			// all else 
			$mtext .= "  <div class='row'><strong>all others</strong></div>\n";
			$mtext .= "  <div class='row'>\n";
			foreach ($smethods as $m ) {
				if ( ! $m->isConstructor() && !($m->isProtected() || $m->isPublic()) ) {
					$mtext .= "    <div class='col-xs-6 col-sm-4 col-md-4' style='padding-bottom: 5px;'><a class='' href='#method_".$m->name."'>".$m->name."</a></div>"; } }
			$mtext .= "  </div>\n";

			$mtext .= "</div>\n";

			// Add functionality to call each method
			$mtext .= "<div class='container'>\n";
			$mtext .= "  <div class='row'>\n";
			$mid = 1;
			$k = 0;
			foreach ($methods as $m ) {
				if ( ! ($m->isConstructor() || $m->name == "__get" ) ) {
					$mtext .= "<div class='col-xs-6 col-sm-4 col-md-4 well'>";
					$mtext .= "<div id='method_".$m->name."'><a name='method_".$m->name."'></a></div>";
					$mtext .= "<form id='form".$mid."'>";
					$mtext .= "<p><small>".$rc->name."-></small></p>";
					$mtext .= "<fieldset><legend>".$m->name."</legend>";
					//$mtext .= "<div class='lead'>".$m->name."</div>";
					$mtext .= "<input type='hidden' name='fn' value='".$m->name."' ></input>";
					$mtext .= "<input type='hidden' name='model' value='".$model."' ></input>";
					$dc = $m->getDocComment();
					$mtext .= "<div style='margin-bottom: 7px;'>";
					if ( $dc !== FALSE ) { $mtext .= "<a class='btn btn-xs btn-info' onclick='toggleDiv(\"doc_method_".$m->name."\"); return false;'>toggle doc</a>"; }
					$mtext .="</div>";
					if ( $dc !== FALSE ) { $mtext .= "<pre id='doc_method_".$m->name."' style='display: none;'><code style='font-size: 60%; line-height: 0;'>".join("\n", array_map("ltrim", explode("\n", $dc)))."</code></pre>"; }


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
					$mtext .= "<a class='btn btn-default btn-sm' href='/index.php/".get_class($this)."/run_unit_tests/".$model."#method_".$m->name."' alt='link to unit test of method'>unit test</a>\n";
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

			$mtext .= "  </div>\n";
			$mtext .= "</div>\n";


		} else {
			$bod = "<div>Your CIModelTester controller is shutting down because your CodeIgniter project is in a non development state.</div>"; }

		$this->pdata["title"] = "<span class='label label-primary'>".$model.":</span> <small>Call Methods</small>";//'Adder Model';
		$this->pdata["body"] = $mtext;
		echo $this->applyTemplate();
    }

	// --------------------------------------------------------------------- 
	// run_unit_tests($_model)
	// --------------------------------------------------------------------- 
	// This calls the unit tests on the model. 
	//  (see method 'model' above regarding $_model)
	// --------------------------------------------------------------------- 
	public function run_unit_tests($_model) {
		$srcmodel = $testmodel = $varmodel = $tvarmodel = "";
		$this->gennames(null,func_get_args(),$srcmodel,$testmodel,$varmodel,$tvarmodel);

		$bod = "";
		$bod .= $this->applyNavigationTemplate($srcmodel,'run_unit_tests');

		if ( ENVIRONMENT != "testing" ) {
			$bod .= "<div class='lead'>Not in testing environment. Currently set to ".ENVIRONMENT.".</div>";
		} else if( ! file_exists(APPPATH."models/$testmodel.php") ){ //file_exists(APPPATH."models/tests/test_$model.php") 
			$bod .= "<div class='container'>";
			$bod .= "  <div class='row'>";
			$bod .= "    <div class='lead'>No testing model. create a model in '".APPPATH."models/$testmodel.php'</div>";
			$bod .= "    <h3>Example Code:</h3>";
			$bod .= "    <pre><code>".
"&lt;?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');\n".
"\n".
"require_once('LoadTestData.php');\n".
"\n".
"class ".$tvarmodel." extends ".$srcmodel."\n".
"{\n".
"    function __construct() { parent::__construct(); }\n".
"\n".
"    // Generic Model tests\n".
"    public function test() {\n".
"        \$retval = LoadData1(); // User created function in LoadTestData.php\n".
"        [GENERIC TESTS]\n".
"        return \$retval;\n".
"    }\n".
"\n".
"    public function onExit() {}\n".
"\n".
"    // Tests specific to a method\n".
"    public function test_[YOUR METHOD]() {\n".
"        \$retval = LoadData1();\n".
"        \$retval = pack_ret(\$v = \$this->[YOUR METHOD]());\n".
"        \$this->unit->run(\$v,[EXPECTEDRESULT],[TEST NAME]);\n".
"        return \$retval;\n".
"    }\n".
"};\n".
"</pre></code>";
			$bod .= "  </div>";
			$bod .= "</div>";
		} else {
			$this->load->library("unit_test");
			$rp = $this->load->model($testmodel);

			$bod .= "<div class='container' id='outer_nomethodalert' style='display: none;'>\n".
				    "  <div class='row'>\n".
					"    <div class='alert alert-danger alert-dismissible' role='alert' >\n".
					"      <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>\n".
					"      <div id='nomethodalert'></div></div></div></div>\n";

			// generic test
			$bod .= "<div class='container'><div class='row'><h3>Generic Test</h3></div></div>\n";
			$rr = $this->{$tvarmodel}->test(); //$rr = $this->{$testmodel}->test();
			$bod .= "<div class='container thumbnail'><div>".$this->unit->report()."</div><div>$rr</div></div>";
			$this->unit->results = array();

			$rc = new ReflectionClass(end(explode('/',$testmodel)));
			$methods = $rc->getMethods();
			$smethods = $methods; usort($smethods, "_cmp_methods");

			// find methods, and look for their tests
			$usedtestmap = array(); foreach( $smethods as $m) { if( strpos($m->name,"test_",0) !== false  ) $usedtestmap[$m->name] = '0'; }
			$testable = 0;
			$tested   = 0;
			$tested_failed = 0;
			foreach( $methods as $m) {
				if ( (! $m->isConstructor()) && ($m->name != "__get")   ) {
					if( (strpos($m->name,"test_",0)!== 0) && $m->name != "test" && $m->name != "__get" && $m->name != 'onExit'  ) {
						$testable ++;
						log_message("debug","testable ".$m->name);
						$fnd = false;
						foreach( $methods as $mt) {

							if( $mt->name == "test_".$m->name ) {
								$usedtestmap[$mt->name] = "1";
								$tested++;
								$ret = $this->{$tvarmodel}->{$mt->name}();
								$r_pass = 0; $r_tot = 0; foreach ( $this->unit->result() as $rs ) { if ( $rs['Result'] == 'Passed') {$r_pass++;} $r_tot++; }
								$bod .= $this->applyUnitTestTemplate($r_pass,$r_tot,$srcmodel,$m->name,$this->unit->report(),$ret);
								$this->unit->results = array(); // reset it
								$fnd = true;
								break;
							}
						}
					}
				}
			}

			// Look for methods 'test_*' that were never called. These are probably typos.
			$bodt = "";
			foreach($usedtestmap as $k=>$v) {
				if( $v == 0 ) {
					$bodt .= "<div>Did you mispell the method '$k' in $testmodel?</div>\n";
				}
			}
			if( $bodt != "" ) $bod .="<div class='alert alert-warning'><h3>Possible Error?</h3><p>$bodt</p></div>\n";

			// Final testing stats
			$bod .= "<div class='container'><div class='row'><div class='well'>Tested ".$tested." of ".$testable." methods with $tested_failed failures</div></div></div>";


			// Call onExit method if it exists
			if ( method_exists($this->{$tvarmodel},"onExit") ) 
				$this->{$tvarmodel}->onExit(); 


			// script to see if URL hash is a valid method. run client-side and use jquery
			$bod .= 
"\n<script>\n".
"$(document).ready(function() {\n".
"  if ( window.location.hash != '' ) {\n".
"    if ( $('a[name='+window.location.hash.substring(1)+']').length == 0 ) {\n".
"      $('#nomethodalert').html('<h5>Hmmm...</h5><div class=\"lead\">No ".$varmodel."::'+window.location.hash.substring(8)+'(...) test exists.</div>');\n".
"      $('#outer_nomethodalert').css('display','block');\n".
"    }\n".
"  }\n".
"});\n".
"</script>\n";
		}

		$this->pdata["title"] = "<span class='label label-primary'>".$srcmodel.":</span> <small>Unit Tests</small>";//'Adder Model';
		$this->pdata["body"] = $bod;
		echo $this->applyTemplate();
	}



	// --------------------------------------------------------------------- 
	// applyTemplate
	// --------------------------------------------------------------------- 
	// A hack at applying a standardized page look and feel. Needs a real
	// template like Mustache, but this method means only 1 file to install!
	// --------------------------------------------------------------------- 
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
			"   <script>function toggleDiv(_divid) { $('#'+_divid).toggle('fast'); } </script>".
			"</head>".
			"<body>".
			"<div class='container' style='margin-bottom: 15px;'>".
			"	<div class='row'><div class='col-md-12'><h5>CIModelTester</h5><h1>".$this->pdata['title']."</h1></div></div>".
			"</div>".
			$this->pdata['body'].
			"<div class='container'>".
			"   <div class='row well'><div class='col-md-12'><small>A <a href='http://conquestcreations.com'>Conquest Creations</a> product. Find the <a href='https://github.com/cwingrav/CIModelTester'>Github repo for CIModelTester here.</a> </small></div></div>".
			"</div>".
			"</body>".
			"</html>";
		return $retval;
	}



	// --------------------------------------------------------------------- 
	// --------------------------------------------------------------------- 
	// --------------------------------------------------------------------- 
	protected function applyUnitTestTemplate($_r_pass,$_r_tot,$_srcmodel,$_mname,$_report,$_ret) {
		$retval = "";
		$tested_failed = 0; if ( $_r_pass != $_r_tot ) $tested_failed++;
		$retval .= "<a name='method_$_mname'></a>\n";
		$retval .= "<div class='container'>\n";
		$retval .= "  <div class='row'>\n";
		$retval .= "    <div class='alert ".($tested_failed != 0 ? "alert-danger" : ($_r_tot == 0 ? "alert-warning": "alert-info"))."'>\n";
		$retval .= "        <h4><a href='/index.php/".get_class($this)."/model/".$_srcmodel."#method_".$_mname."' alt='link to method'>".$_mname."</a> Tested</h4>\n";
		$retval .= "        <div>result: [".$_r_pass."/".$_r_tot."] ".($_r_pass==$_r_tot ? 'passed':'failed')." <a onclick='toggleDiv(\"test_".$_mname."\"); return false;' class='btn btn-info btn-xs'>results</a></div>";
		$retval .= "        <div id='test_".$_mname."' style='".($_r_pass==$_r_tot ? 'display: none':'')."'>";
		$retval .= "          <div>".$this->unit->report()."</div>\n";
		$retval .= "          <div><h5>returned:</h5>".print_r($_ret,true)."</div>\n";
		$retval .= "        </div>\n";
		$retval .= "    </div>\n";
		$retval .= "  </div>\n";
		$retval .= "</div>\n";
		return $retval;
	}


	
	// --------------------------------------------------------------------- 
	// --------------------------------------------------------------------- 
	// Controls for switching to another model in the project.
	// --------------------------------------------------------------------- 
	protected function applySwitchModelTemplate($_screen,$_ishidden = false) {
		//$retval  = "<div class='container'>";
		$retval = "<div id='cimt_switchmodel' class='row' style='".($_ishidden?"display:none":"")."'>";
		$retval .="  <div class='col-xs-12'><h4>Select a Model</h4></div>";
		$srcmodel = $testmodel = $varmodel = $tvarmodel = "";
		foreach( $this->mymodels as $k=>$m ) {
			$hastest = 0;
			$this->gennames($m,null,$srcmodel,$testmodel,$varmodel,$tvarmodel);
			foreach($this->mytestmodels as $tm ) { if ( $tm == $testmodel ) { $hastest= 1; break; } }

			$mparts = explode("/",$m);
			$mname  = $mparts[sizeof($mparts)-1];
			array_pop($mparts);
			$mpath  = implode("/",$mparts);
			if ( $mpath == "" ) $mpath = "&nbsp;";
			else $mpath .= "/";

			$retval .= "<div class='col-xs-6 col-sm-4 col-md-3' style='padding-left: 7px; padding-right: 7px;'>";
			$retval .= "  <div class='' style='padding: 7px;'>";
			$retval .= "    <div class='' style='margin-bottom: 7px;'>";
			$retval .= "      <a class='btn btn-xs btn-info'href='/index.php/".get_class($this)."/".$_screen."/".$m."'>";
			$retval .= "        <small style='float:left;'>models/".$mpath."</small><br />";
			$retval .= "        <div class='' sstyle='font-size: 120%;'>".$mname." ".($hastest==1?"<span class='label label-default'>Tests</span>":'')."</div>";
			$retval .= "      </a>";
			$retval .= "    </div>";
			//$retval .= "    <div><a href='/index.php/".get_class($this)."/run_unit_tests/".$m."' class='btn btn-info btn-xs' >run unit tests</a></div>";
			$retval .= "  </div>";
			$retval .= "</div>";
			$retval .= $this->cClearFix($k,6,4,3,3);
		}
		$retval .= "</div>";
		//$retval .= "</div>";
		return $retval;
	}



	// --------------------------------------------------------------------- 
	// modelname2testname(...)
	// --------------------------------------------------------------------- 
	// Utility function to compute the test file name.
	//   $_modelname  - ex. admin/content_model.php
	//   $_CIparamarray  - from CI's passed parameters, ex. func_get_args()
	//
	// Params for $_modelname or $_CIparamarray
	//   ex. "bar_model", array("bar_model") -> tests/test_bar_model.php
	//   ex. "foo/bar_model", array("foo","bar_model") -> admin/tests/content_model.php
	//
	// Names of models to pass to $CI->load->model(...)
	//   $_srcmodel  = "foo/bar_model"             <-file
	//   $_testmodel = "foo/tests/test_bar_model"  <-file
	//
	// Names to invoke the models on a CI object 
	//   $_varmodel  = "bar_model"       <-CI name ex. $this->bar_model
	//   $_tvarmodel = "test_bar_model"  <-CI name ex. $this->test_bar_model
	// --------------------------------------------------------------------- 
	protected function gennames($_modelname,$_CIparamarray,&$_srcmodel,&$_testmodel,&$_varmodel,&$_tvarmodel) {

		// operate on array or modelname 
		$args = $_CIparamarray;
		if ( $args == null) { $args = explode("/",$_modelname); } 

		// the name of the model in $this-> 
		$_varmodel  = $args[sizeof($args)-1]; 
		$_tvarmodel = "test_".$_varmodel;

		// turn last entry into $_varmodel to make $_srcmodel
		$args[sizeof($args)-1] = "".$_varmodel;
		$_srcmodel  = implode('/',$args); // array to string

		// turn last entry into $_tvarmodel to make $_testmodel
		$args[sizeof($args)-1] = "tests/".$_tvarmodel;
		$_testmodel = implode('/', $args); // turn array to string
		//log_message("debug","    : $_srcmodel $_testmodel $_varmodel $_tvarmodel");
	}


	// --------------------------------------------------------------------- 
	// --------------------------------------------------------------------- 
	// Add navigation to top of non-home screens.
	// --------------------------------------------------------------------- 
	protected function applyNavigationTemplate($_model,$_screen) {
		$retval  = "<div class='container well'>";
		$retval .= "  <div class=''>";
		$retval .= "    <a class='btn btn-info btn-xs' href='/index.php/CIModelTester'><span class='glyphicon glyphicon-home'></span></a>";
		$retval .= "    <a class='btn btn-info btn-xs' href='javascript:history.go(-1)'>&lt; back </a>";
		if ( $this->testinglink != null ) {
			$retval .= "    <a href='".$this->testinglink."' class='btn btn-info btn-xs'>run tests</a>"; }
		$retval .= "    <a class='btn btn-xs btn-info' onclick='toggleDiv(\"cimt_switchmodel\"); return false;'>switch model</a>";
		switch ($_screen) {
			case 'model':
				$retval .= "    <a href='/index.php/".get_class($this)."/run_unit_tests/".$_model."' class='btn btn-primary btn-xs' >run unit tests</a>";
				break;
			case 'run_unit_tests':
				$retval .= "    <a href='/index.php/".get_class($this)."/model/".$_model."' class='btn btn-primary btn-xs' >call methods</a>";
				break;
			default: log_message('debug',"ERROR: Unknown navigation screen '$_screen'"); break;
		};
		$retval .= "  </div>";
		$retval .= $this->applySwitchModelTemplate($_screen,true);
		$retval .= "</div>";
		return $retval;
	}

	// --------------------------------------------------------------------- 
	// recursivelyAddModelsFromMap
	// --------------------------------------------------------------------- 
	//  Looks through your APPPATH."/models" directory looking for 
	//  "*_model.php" files to load. You don't need to call.
	// --------------------------------------------------------------------- 
	protected function recursivelyAddModelsFromMap($_map,$_path = "") {
		foreach ( $_map as $k=>$m ) {
			if ( $k === 'tests' ) {}  // skip
			else if ( is_array($m) ) { $this->recursivelyAddModelsFromMap($m,$_path.$k."/"); } // recurse
			else {
				$v = strrpos($m,"_model.php",-10);
				if ( $v !== false ) { array_push($this->mymodels,rtrim($_path.$m,".php")); }
			}
		}
	}


	// --------------------------------------------------------------------- 
	// recursivelyAddTestModelsFromMap
	// --------------------------------------------------------------------- 
	//  Looks through your APPPATH."/models" directory looking for 
	//  "tests/test_*_model.php" files to load. You don't need to call.
	// --------------------------------------------------------------------- 
	protected function recursivelyAddTestModelsFromMap($_map,$_parentistest,$_path = "") {
		foreach ( $_map as $k=>$m ) {
			if ( $k === 'tests') { $this->recursivelyAddTestModelsFromMap($m,true,$_path.$k."/"); } // recurse
			else if ( is_array($m) ) { $this->recursivelyAddTestModelsFromMap($m,false,$_path.$k."/"); } // recurse
			else {
				if ( $_parentistest ) {
					$v = strrpos($m,"_model.php",-10);
					$vv = strrpos($m,"test_",0);
					if ( $v !== false && $vv !== false ) { array_push($this->mytestmodels,rtrim($_path.$m,".php")); }
				}
			}
		}
	}


	// --------------------------------------------------------------------- 
	// cClearFix
	// --------------------------------------------------------------------- 
	// A hack to Boostrap so the columns in a row are the same height. See,
	// I care about how things look...
	// --------------------------------------------------------------------- 
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
