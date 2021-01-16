<?php

$ra = array();

foreach (new DirectoryIterator(CATSDIR_IMG . "papers") as $file) {
    if ($file->isDot()) continue;
    array_push($ra, $file->getBasename(".png"));
}
$template = 
<<<Template
<h5 id='info'>Click on a preview to print that paper.</h5>
<div class='container' id='container'></div>
<p id='tip' style='display: none'>
    Internet Explorer, Edge, Safari and Firefox do not support automatic page orientation.
    If you want to print a landscape-oriented paper, please change the orintation to 'landscape'
    in the print settings.
</p>
<template>
    <div class='col-md-2'></div>
</template>
<script>
const dir = "[[CATSDIR_IMG]]" + "papers/";
const landscapes = [];
var paths = JSON.parse('[[obj]]');
var templ = document.getElementsByTagName("template")[0];
var rows = [];
var currentIndex = -1;
var pageRule = document.createElement('style');
document.addEventListener("DOMContentLoaded", function() {
document.body.appendChild(pageRule);
if (unsupported()) {
    let tip = document.getElementById("tip");
    tip.style.display = "block";
    if (/constructor/i.test(window.HTMLElement) || 
        (function (p) { return p.toString() === "[object SafariRemoteNotification]"; })
        (!window['safari'] || safari.pushNotification)) {
        tip.innerHTML += "<br/><br/> On Safari, you may also want to change the print margins"
            + " to 0mm, for larger printing. You can find out how to do that "
            + "<a href='http://www.mintprintables.com/print-tips/adjust-margins-osx/'>here</a>.";
    }
}
addRow();
for (var file of paths.values()) {
    let img = document.createElement("img");
    img.src = dir + file + ".png";
    if (file.endsWith(" LANDSCAPE")) {
        file = file.substring(0, file.length - 10);
        landscapes.push(file);
    }
    img.alt = file;
    img.classList.add("thumbnail");
    let cont = templ.content.cloneNode(true).querySelector(".col-md-2");
    let clon = img.cloneNode(true);
    cont.appendChild(clon);
    insert(cont);
    clon.addEventListener("click", printImg);
}
});
function insert(x) {
    if (rows[currentIndex].isFull)  addRow();
    rows[currentIndex].appendChild(x);
    if (rows[currentIndex].childElementCount === 6)
        rows[currentIndex].isFull = true;
}
function addRow() {
    let temp = document.createElement("div");
    temp.classList.add("row");
    document.getElementById("container").appendChild(temp);
    rows.push(temp);
    currentIndex++;
    return rows[currentIndex];
}
function printImg() {
    let printImg = this.cloneNode(true);
    printImg.classList.add("printing");
    printImg.classList.remove("thumbnail");
    if (landscapes.includes(printImg.alt)) {
        pageRule.innerHTML = 
        "@page {" +
            "size: letter landscape;" +
            "margin: 0;" +
        "}";
    } else {
        pageRule.innerHTML = 
        "@page {" +
            "size: letter portrait;" +
            "margin: 0;" +
        "}";
    }
    //document.body.appendChild(printImg);
var w = window.open();
w.document.write( "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">" );
w.document.write( "<html>" );
w.document.write( "<head>" );
w.document.write( "<meta charset='utf-8'>" );
w.document.write( "</head><body>" );
w.document.write( "<style>html,body,img{width:100%;height:100%;object-fit: contain;}</style>" );
w.document.write( document.getElementById('printStyle').outerHTML );
w.document.write( pageRule.outerHTML );
w.document.write( printImg.outerHTML );
w.document.write('<script type="text/javascript">function closeme(){window.close();}setTimeout(closeme,50);window.print();<\/script></body></html>');
w.document.close();
    //print();
    //document.body.removeChild(printImg);
}
function unsupported() {
    if ((!!window.chrome && !!window.chrome.webstore) ||
            !!window.opera || navigator.userAgent.indexOf(' OPR/') >= 0) {
        return false;
    }
    return true; 
}
</script>
<style id='printStyle' >
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
@media print {
    body :not(.printing) {
        display: none !important;
    }
    .printing {
        display: block;
        position: absolute;
        top: 0;
        right: 5px;
        width: 100%;
        max-width: 100%;
        height: 100%;
        max-height: 100%;
        object-fit: contain;
        margin: 0;
        padding: 0;
    }
    body {
        margin: 0;
        padding: 0;
        width: 100%;
    }
    html {
        padding: 0px;
        margin: 0px;
        width: 100%;
    }
}
#tip {
    width: 50%;
    margin: auto;
    background-color: orange;
    border: 2px solid orange;
    border-style: inset outset outset inset;
    border-radius: 10px;
    padding: 10px;
    margin-top: 20px;
}
#info {
    text-align: center;
}
</style>
Template;
$template = str_replace("[[CATSDIR_IMG]]", "w/img/", $template);
$template = str_replace("[[obj]]", json_encode($ra), $template);

$s .= $template;
$s;