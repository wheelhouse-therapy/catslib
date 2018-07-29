<?php

$ra = array();

foreach (new DirectoryIterator(CATSDIR_IMG + "/papers") as $file) {
    if ($file->isDot()) continue;
    array_push($ra, $file->getBasename(".pdf"));
}
$template = 
<<<Template
<div class='container' id='container'>
</div>
<template>
<div class='col-md-3' id='insert-here'>
</div>
</template>
<script>
const dir = [[CATSLIB_IMG]];
var paths = JSON.parse("[[obj]]");
var template = document.getElementsByTagName("template")[0];
for (var file of paths.values()) {
    var img = document.createElement("img");
    img.src = dir + file + ".pdf";
    img.alt = file;
    img.classList.add("thumbnail");
    var cont = template.content.cloneNode(true).querySelector("#insert-here");
    cont.appendChild(img);
    document.getElementById("container").appendChild(cont);
}
</script>
<style>
.thumbnail {
    display: block;
    margin: auto;
    padding: 5px;
}
.thumbnail:hover {
    box-shadow: 2px gray 5px;
}
</style>
Template;
$template = str_replace($template, "[[CATSLIB_IMG]]", CATSLIB_IMG);
$template = str_replace($template, "[[obj]]", json_encode($ra));

$s .= $template;
?>