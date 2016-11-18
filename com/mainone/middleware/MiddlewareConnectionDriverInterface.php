<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace com\mainone\middleware;

/**
 *
 * @author Kolade.Ige
 */
interface MiddlewareConnectionDriverInterface {
    public function setSelectedFields(array $select = []);
    public function setConnectionToken(stdClass &$token_response);
    public function setKeyField($key_field = NULL);
    public function getItemsByIds(array $Ids = [], array $notable_fields = []);
    public function getItemsByFieldValues();
    public function getItemsByFilter();
}
