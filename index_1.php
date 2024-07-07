<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WSI Viewer with Annotorious Annotations and Draggable Navigator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@recogito/annotorious-openseadragon@2.7.18/dist/annotorious.min.css">
    <script src="https://cdn.jsdelivr.net/npm/openseadragon/build/openseadragon/openseadragon.min.js"></script>
    <script src="https://openseadragon.github.io/svg-overlay/openseadragon-svg-overlay.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@recogito/annotorious-openseadragon@2.7.18/dist/openseadragon-annotorious.min.js"></script>
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://d3js.org/d3.v5.min.js"></script>
    <script src="https://harshalitalele.github.io/OpenSeadragonDraggableNavigator/openseadragon-draggable-navigator.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@recogito/annotorious-selector-pack@latest/dist/annotorious-selector-pack.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@recogito/annotorious-toolbar@latest/dist/annotorious-toolbar.min.js"></script>
    <script src="https://faruq1997.github.io/tools/openseadragon-filtering.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.2/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style type="text/css">
        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
        }
        .container-fluid {
            height: 100%;
            display: flex;
            flex-direction: column;
            padding: 0;
        }
        .header {
            background-color: #343a40;
            color: white;
            padding: 10px 20px;
            text-align: center;
        }
        #contentDiv {
            flex-grow: 1;
            position: relative;
            background-color: #f8f9fa;
        }
        #infoDiv {
            padding: 10px;
            background-color: #f8f9fa;
            border-left: 1px solid #ddd;
            font-family: monospace;
            overflow: auto;
            height: 100%;
            white-space: pre;
            display: none;
            width: 300px;
            position: absolute;
            right: 0;
            top: 0;
            z-index: 1000;
        }
        @media (max-width: 768px) {
            .navigator {
                width: 100px;
                height: 100px;
            }
            #infoDiv {
                width: 100%;
                position: static;
                height: auto;
            }
        }
        .toolbar-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 10px 0;
        }
    </style>
    <script>
        var App = {
            init: function() {
                var self = this;

                this.viewer = OpenSeadragon({
                    id: "contentDiv",
                    prefixUrl: "https://cdn.jsdelivr.net/npm/openseadragon/build/openseadragon/images/",
                    showRotationControl: true,
                    showFlipControl: true,
                    tileSources: ["./132622.dzi"],
                    showNavigator: true,
                    navigatorAutoFade: false,
                    showNavigationControl: true,
                });

                this.viewer.setNavigatorDraggable(true);

                var overlay = this.viewer.svgOverlay();
                var d3Overlay = d3.select(overlay.node());

                // Initialize Annotorious
                var anno = OpenSeadragon.Annotorious(this.viewer);
                
                // Initialize selector pack
                Annotorious.SelectorPack(anno, {
                    tools: ['point', 'circle', 'freehand', 'rect', 'polygon']
                });

                // Initialize OpenSeadragonHTMLelements
                // this.viewer.HTMLelements();
                
                // Initialize toolbar
                Annotorious.Toolbar(anno, document.getElementById('my-toolbar-container'),{'withTooltip': true});

                // Helper function to download data
                function download(data, filename, type) {
                    var file = new Blob([data], {type: type});
                    var a = document.createElement("a"),
                            url = URL.createObjectURL(file);
                    a.href = url;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    setTimeout(function() {
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);  
                    }, 0); 
                }

                // Get the image filename without extension
                var imageName = "132622"; // Using the filename without extension as base for export filenames

                // Export annotations as Default JSON
                document.getElementById('export-default-json').addEventListener('click', function() {
                    var annotations = anno.getAnnotations();
                    
                    var boxes = annotations.map(function(annotation) {
                        var selector = annotation.target.selector.value;
                        var coords = selector.replace('xywh=pixel:', '').split(',');
                        return {
                            label: annotation.body[0].value,
                            x: parseFloat(coords[0]),
                            y: parseFloat(coords[1]),
                            width: parseFloat(coords[2]),
                            height: parseFloat(coords[3])
                        };
                    });

                    var exportData = {
                        boxes: boxes,
                        height: self.viewer.world.getItemAt(0).getContentSize().y,
                        width: self.viewer.world.getItemAt(0).getContentSize().x,
                        key: "132622.dzi"
                    };

                    var dataStr = JSON.stringify(exportData, null, 2);
                    download(dataStr, imageName + ".json", "application/json");
                });

                // Export annotations as COCO JSON
                document.getElementById('export-coco-json').addEventListener('click', function() {
                    var annotations = anno.getAnnotations();
                    
                    // Extract unique categories
                    var categories = [];
                    var categoryMap = {};
                    annotations.forEach(function(annotation) {
                        var label = annotation.body[0].value;
                        if (!categoryMap[label]) {
                            categoryMap[label] = {
                                id: categories.length,
                                name: label,
                                supercategory: "none"
                            };
                            categories.push(categoryMap[label]);
                        }
                    });

                    var boxes = annotations.map(function(annotation, id) {
                        var selector = annotation.target.selector.value;
                        var coords = selector.replace('xywh=pixel:', '').split(',');
                        var x = parseFloat(coords[0]);
                        var y = parseFloat(coords[1]);
                        var width = parseFloat(coords[2]);
                        var height = parseFloat(coords[3]);

                        return {
                            id: id,
                            image_id: 0,
                            category_id: categoryMap[annotation.body[0].value].id,
                            segmentation: [
                                [x, y, x + width, y, x + width, y + height, x, y + height]
                            ],
                            area: width * height,
                            bbox: [x, y, width, height],
                            isCrowd: 0
                        };
                    });

                    var cocoData = {
                        images: [{
                            id: 0,
                            width: self.viewer.world.getItemAt(0).getContentSize().x,
                            height: self.viewer.world.getItemAt(0).getContentSize().y,
                            file_name: "132622.dzi",
                            license: 1,
                            date_captured: ""
                        }],
                        annotations: boxes,
                        licenses: [{
                            id: 1,
                            name: "Unknown",
                            url: ""
                        }],
                        categories: categories
                    };

                    var dataStr = JSON.stringify(cocoData, null, 2);
                    download(dataStr, imageName + ".json", "application/json");
                });

                // Export annotations as YOLO TXT with classes.txt
                document.getElementById('export-yolo-txt').addEventListener('click', function() {
                    var annotations = anno.getAnnotations();
                    
                    var imageWidth = self.viewer.world.getItemAt(0).getContentSize().x;
                    var imageHeight = self.viewer.world.getItemAt(0).getContentSize().y;

                    // Extract unique categories
                    var categories = [];
                    var categoryMap = {};
                    annotations.forEach(function(annotation) {
                        var label = annotation.body[0].value;
                        if (!categoryMap[label]) {
                            categoryMap[label] = categories.length;
                            categories.push(label);
                        }
                    });

                    var yoloData = annotations.map(function(annotation) {
                        var selector = annotation.target.selector.value;
                        var coords = selector.replace('xywh=pixel:', '').split(',');

                        var x_center = (parseFloat(coords[0]) + parseFloat(coords[2]) / 2) / imageWidth;
                        var y_center = (parseFloat(coords[1]) + parseFloat(coords[3]) / 2) / imageHeight;
                        var width = parseFloat(coords[2]) / imageWidth;
                        var height = parseFloat(coords[3]) / imageHeight;

                        return `${categoryMap[annotation.body[0].value]} ${x_center} ${y_center} ${width} ${height}`;
                    }).join('\n');

                    var classesData = categories.join('\n');

                    download(yoloData, imageName + ".txt", "text/plain");
                    download(classesData, "classes.txt", "text/plain");
                });

                // Export annotations as VOC XML
                document.getElementById('export-voc-xml').addEventListener('click', function() {
                    var annotations = anno.getAnnotations();
                    
                    var imageWidth = self.viewer.world.getItemAt(0).getContentSize().x;
                    var imageHeight = self.viewer.world.getItemAt(0).getContentSize().y;
                    var imageFilename = "132622.dzi";

                    var xmlContent = `
<annotation>
  <folder>annotations</folder>
  <filename>${imageFilename}</filename>
  <path>${imageFilename}</path>
  <source>
    <database>Unknown</database>
  </source>
  <size>
    <width>${imageWidth}</width>
    <height>${imageHeight}</height>
    <depth>3</depth>
  </size>
  <segmented>0</segmented>
`;

                    annotations.forEach(function(annotation) {
                        var selector = annotation.target.selector.value;
                        var coords = selector.replace('xywh=pixel:', '').split(',');

                        xmlContent += `
  <object>
    <name>${annotation.body[0].value}</name>
    <pose>Unspecified</pose>
    <truncated>0</truncated>
    <difficult>0</difficult>
    <bndbox>
      <xmin>${parseFloat(coords[0])}</xmin>
      <ymin>${parseFloat(coords[1])}</ymin>
      <xmax>${parseFloat(coords[0]) + parseFloat(coords[2])}</xmax>
      <ymax>${parseFloat(coords[1]) + parseFloat(coords[3])}</ymax>
    </bndbox>
  </object>
`;
                    });

                    xmlContent += `</annotation>`;
                    download(xmlContent, imageName + ".xml", "application/xml");
                });

                // Update info
                function updateInfo() {
                    var tiledImage = self.viewer.world.getItemAt(0);
                    if (!tiledImage) return;

                    var info = {
                        navigator: {
                            appVersion: navigator.appVersion,
                            userAgent: navigator.userAgent
                        },
                        osdBrowser: OpenSeadragon.Browser,
                        osdMouseTracker: {
                            wheelEventName: OpenSeadragon.MouseTracker.wheelEventName,
                            havePointerCapture: OpenSeadragon.MouseTracker.havePointerCapture,
                            havePointerEvents: OpenSeadragon.MouseTracker.havePointerEvents
                        },
                        haveImage: !!self.viewer.source,
                        haveMouse: self.viewer.innerTracker.haveMouse,
                        imageProps: self.viewer.source ? {
                            imgWidth: self.viewer.source.dimensions.x,
                            imgHeight: self.viewer.source.dimensions.y,
                            imgAspectRatio: self.viewer.source.aspectRatio,
                            minZoom: self.viewer.viewport.getMinZoom(),
                            maxZoom: self.viewer.viewport.getMaxZoom()
                        } : {},
                        viewerProps: {
                            osdContainerWidth: self.viewer.container.clientWidth,
                            osdContainerHeight: self.viewer.container.clientHeight,
                            osdZoom: self.viewer.viewport.getZoom(),
                            osdBoundsX: self.viewer.viewport.getBounds().x,
                            osdBoundsY: self.viewer.viewport.getBounds().y,
                            osdBoundsWidth: self.viewer.viewport.getBounds().width,
                            osdBoundsHeight: self.viewer.viewport.getBounds().height,
                            osdTiledImageBoundsX: tiledImage.getBounds().x,
                            osdTiledImageBoundsY: tiledImage.getBounds().y,
                            osdTiledImageBoundsWidth: tiledImage.getBounds().width,
                            osdTiledImageBoundsHeight: tiledImage.getBounds().height,
                            zoomFactor: self.viewer.viewport.getZoom(true),
                            viewportWidth: self.viewer.viewport.getBounds(true).width,
                            viewportHeight: self.viewer.viewport.getBounds(true).height,
                            viewportOriginX: self.viewer.viewport.getBounds(true).x,
                            viewportOriginY: self.viewer.viewport.getBounds(true).y,
                            viewportCenterX: self.viewer.viewport.getCenter().x,
                            viewportCenterY: self.viewer.viewport.getCenter().y
                        }
                    };

                    document.getElementById('infoDiv').textContent = JSON.stringify(info, null, 2) + '\n';
                }

                this.viewer.addHandler('animation', updateInfo);
                this.viewer.addHandler('open', updateInfo);

                updateInfo();

            }
        };

        $(document).ready(function() {
            App.init();

            // Show/Hide button functionality
            $('#toggleInfoBtn').click(function() {
                $('#infoDiv').toggle();
                $(this).text(function(i, text){
                    return text === "Show Info" ? "Hide Info" : "Show Info";
                });
            });

            $('#screenshotBtn').click(function() {
                // Hide the navigator
                App.viewer.navigator.element.style.display = 'none';
                document.getElementById('osd-draggable-nav').style.display = 'none';
                document.getElementById('infoDiv').style.display = 'none';
                

                html2canvas(document.querySelector("#contentDiv")).then(canvas => {
                    var link = document.createElement('a');
                    link.download = 'screenshot.png';
                    link.href = canvas.toDataURL();
                    link.click();

                    // Show the navigator again
                    App.viewer.navigator.element.style.display = 'block';
                    document.getElementById('osd-draggable-nav').style.display = 'block';
                });
            });

            // Apply filters
            $('#applyFilterBtn').click(function() {
                var filters = [];
                if ($('#thresholdFilter').prop('checked')) {
                    var thresholdValue = parseInt($('#thresholdValue').val(), 10);
                    filters.push({
                        processors: OpenSeadragon.Filters.THRESHOLDING(thresholdValue)
                    });
                }
                if ($('#brightnessFilter').prop('checked')) {
                    var brightnessValue = parseInt($('#brightnessValue').val(), 10);
                    filters.push({
                        processors: OpenSeadragon.Filters.BRIGHTNESS(brightnessValue)
                    });
                }
                if ($('#contrastFilter').prop('checked')) {
                    var contrastValue = parseFloat($('#contrastValue').val());
                    filters.push({
                        processors: OpenSeadragon.Filters.CONTRAST(contrastValue)
                    });
                }
                if ($('#gammaFilter').prop('checked')) {
                    var gammaValue = parseFloat($('#gammaValue').val());
                    filters.push({
                        processors: OpenSeadragon.Filters.GAMMA(gammaValue)
                    });
                }
                if ($('#greyscaleFilter').prop('checked')) {
                    filters.push({
                        processors: OpenSeadragon.Filters.GREYSCALE()
                    });
                }
                if ($('#invertFilter').prop('checked')) {
                    filters.push({
                        processors: OpenSeadragon.Filters.INVERT()
                    });
                }

                App.viewer.setFilterOptions({
                    filters: filters
                });
            });

            // Reset all filters
            $('#resetFiltersBtn').click(function() {
                App.viewer.setFilterOptions({
                    filters: []
                });
                $('input[type=checkbox]').prop('checked', false);
                $('input[type=range]').each(function() {
                    var defaultValue = $(this).attr('value');
                    $(this).val(defaultValue);
                });
            });

        });
    </script>
</head>
<body>
    <div class="container-fluid">
        <div class="header">
            <h1><i class="fa-solid fa-microscope"></i> WSI Viewer with Annotations</h1>
        </div>
        <div class="toolbar-container mx-1">
            <div id="my-toolbar-container"></div>
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <div class="dropdown">
                    <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Export Annotation
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" id="export-default-json">Default JSON</a></li>
                        <li><a class="dropdown-item" id="export-coco-json">COCO JSON</a></li>
                        <li><a class="dropdown-item" id="export-yolo-txt">YOLO TXT</a></li>
                        <li><a class="dropdown-item" id="export-voc-xml">VOC XML</a></li>
                    </ul>
                </div>
                <!-- Button trigger modal -->
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
                  Terapkan Filter
                </button>
                <button id="screenshotBtn" class="btn btn-outline-success">Screenshot</button>
                <button id="toggleInfoBtn" class="btn btn-outline-primary">Show Info</button>
            </div>
        </div>
        <div id="contentDiv">
            <div id="navigatorDiv"></div>
            <div id="infoDiv"></div>
        </div>
    </div>

    <!-- Filter Modal -->
    <div class="modal fade" id="filterModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="filterModalLabel">Filter</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="thresholdFilter">
                            <label class="form-check-label" for="thresholdFilter">Threshold</label>
                            <input type="range" class="form-range" id="thresholdValue" min="0" max="255" step="1" value="128">
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="brightnessFilter">
                            <label class="form-check-label" for="brightnessFilter">Brightness</label>
                            <input type="range" class="form-range" id="brightnessValue" min="-255" max="255" step="1" value="0">
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="contrastFilter">
                            <label class="form-check-label" for="contrastFilter">Contrast</label>
                            <input type="range" class="form-range" id="contrastValue" min="0" max="4" step="0.1" value="1">
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="gammaFilter">
                            <label class="form-check-label" for="gammaFilter">Gamma</label>
                            <input type="range" class="form-range" id="gammaValue" min="0.1" max="7" step="0.1" value="1">
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="greyscaleFilter">
                            <label class="form-check-label" for="greyscaleFilter">Greyscale</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="invertFilter">
                            <label class="form-check-label" for="invertFilter">Invert</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="applyFilterBtn">Apply Filter</button>
                    <button type="button" class="btn btn-warning" id="resetFiltersBtn">Reset All Filters</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
