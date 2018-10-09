<?php
function SQL($query, $parameters=array()) {
    $preparedStatement = Lucinda\SQL\ConnectionSingleton::getInstance()->createPreparedStatement();
    $preparedStatement->prepare($query);
    return $preparedStatement->execute($parameters);
}