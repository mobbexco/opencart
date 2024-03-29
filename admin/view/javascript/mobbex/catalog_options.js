
addEventListener("DOMContentLoaded", (event) => {

    //Get DOM elements
    let nav           = document.querySelector(`#form-${catalogType} .nav-tabs`);
    let tabContent    = document.querySelector(`#form-${catalogType} .tab-content`);
    let mobbexTab     = document.createElement('LI')
    let mobbexContent = document.querySelector("#tab-mobbex");

    //Insert mobbex tab
    mobbexTab.innerHTML = '<a href="#tab-mobbex" data-toggle="tab">Mobbex</a>';
    nav.appendChild(mobbexTab);
    
    //Insert mobbex content
    tabContent.appendChild(mobbexContent);
    mobbexContent.removeAttribute('style');

});