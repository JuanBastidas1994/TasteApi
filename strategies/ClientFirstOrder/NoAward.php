<?php

class NoAward implements ClientFirstOrderInterface {


    public function setAward($invoice_id){
        return false;
    }

}