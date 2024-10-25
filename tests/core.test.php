<?php

test('auth can use existing database connnection', function () {
    $db = connectToDatabase();
    
    expect($db)->toBeInstanceOf(\Leaf\Db::class);
});
