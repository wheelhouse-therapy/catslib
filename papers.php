<?php

$ra = array();

foreach (new DirectoryIterator(CATSDIR_IMG . "papers") as $file) {
    if ($file->isDot()) continue;
    array_push($ra, $file->getBasename(".png"));
}
$template = 
<<<Template
<div class='container' id='container'></div>
<template>
    <div class='col-md-2'></div>
</template>
<script>
const dir = "[[CATSDIR_IMG]]" + "papers/";
var paths = JSON.parse('[[obj]]');
var template = document.getElementsByTagName("template")[0];
var currentRow = null;
debugger;
for (var file of paths.values()) {
    var img = document.createElement("img");
    img.src = dir + file + ".png";
    img.alt = file;
    img.classList.add("thumbnail");
    var cont = template.content.cloneNode(true).querySelector(".col-md-2");
    cont.appendChild(img);
    insert(cont);
}
!function addLast() {
    if (currentRow === null) return;
    document.getElementById("container").appendChild(currentRow.cloneNode(true));
}();
function insert(x) {
    if (currentRow == null) {
        currentRow = document.createElement("div");
        currentRow.classList.add("row");
    }
    currentRow.appendChild(x);
    if (currentRow.childElementCount === 6) {
        document.getElementById("container").appendChild(currentRow.cloneNode(true));
        currentRow = null;
    }
}

</script>
<style>
.thumbnail {
    display: block;
    margin: auto;
    padding: 5px;
    max-width: 100%;
    transition: box-shadow 0.5s;
    cursor: pointer;
    margin-top: 20px;
}
.thumbnail:hover {
    box-shadow: 5px 5px 5px gray, -1px -1px 5px grey;
}
</style>
Template;
$template = str_replace("[[CATSDIR_IMG]]", "w/img/", $template);
$template = str_replace("[[obj]]", json_encode($ra), $template);

$s .= $template;
?>