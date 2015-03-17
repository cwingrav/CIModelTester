# ModelTester
A CodeIgniter interactive model tester web interface. Directly call your model functionality for testing and debugging.

## Usage
After installed, you 

## Installation
* Copy modeltester.php into your CodeIgnite 'libraries' folder. 
* Create a new controller MyModelTester.php in your 'controllers' folder, passing in an array of the models you wish to test. ex "array('model1_model','model2_model')".
```
<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH.'libraries/modeltester.php');
class MyModelTester extends ModelTester {
    public function __construct() {
        parent::__construct(array(...my controllers here...));}}
?>
```
* Load it in your browser:  mydomain/index.php/MyModelTester
