<?php 
 IncludeJS(GetPlugin('RefferalManagement')->getPath().'/js/SpatialDocumentPreview.js')

?>

SpatialDocumentPreview.setClearTile(tile, control);
SpatialDocumentPreview.setMap(map);

tile.disable();

tile.addEvent('click',function(){
    
    
    
});
var getFiles=window.GetSpatialFiles||window.parent.GetSpatialFiles
if(getFiles){
    var files = getFiles();
    if(files.length){
        SpatialDocumentPreview.show(files);
    }
}