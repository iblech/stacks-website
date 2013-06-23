<?php
$config = parse_ini_file("../../config.ini");

// TODO would it be interesting to have a special "section" view, in which the tags inside a section are immediate child nodes of the root node, thus making these graphs more interesting?

require_once("../general.php");

  // TODO get a node count from the database
  $filename = "../../data/" . $_GET["tag"] . "-force.json";
  $filesize = filesize($filename);
  $size = 800 + 10 * $filesize / 1000;
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <style>
      .link {
        stroke: #999;
        stroke-opacity: .6;
      }
      
      body {
        width: <?php print $size; ?>px;
        height: <?php print $size; ?>px;
      }
  
    </style>
    <link rel='stylesheet' type='text/css' href='<?php print href("css/graphs.css"); ?>'>
    <link rel='stylesheet' type='text/css' href='<?php print href("css/tag.css"); ?>'>

    <script src="http://d3js.org/d3.v3.min.js"></script>
    <script src="http://code.jquery.com/jquery-1.9.1.js"></script>
    <script src="http://code.jquery.com/ui/1.10.3/jquery-ui.js"></script>
<?php
print printMathJax();
?>

    <script src="<?php print href("js/graphs.js"); ?>"></script>
    <script type="text/javascript">
      var colorMapping;
  
      function toggleLegend() {
        $("div.legend").hide();

        switch (colorMapping) {
          case global["colorHeatMax"]:
            $("div#legendHeatMax").show();
            return;
          case global["colorHeatMin"]:
            $("div#legendHeatMin").show();
            return;
          case global["colorType"]:
            $("div#legendType").show();
            return;
          case global["colorChapters"]:
            $("div#legendChapters").show();
            return;
        }
      }

      function toggleHeatMin() {
        global["node"].style("fill", global["colorHeatMin"]);

        colorMapping = global["colorHeatMin"];
        toggleLegend();
      }
      
      function toggleHeatMax() {
        global["node"].style("fill", global["colorHeatMax"]);

        colorMapping = global["colorHeatMax"];
        toggleLegend();
      }

      function toggleType() {
        global["node"].style("fill", global["colorType"]);

        colorMapping = global["colorType"];
        toggleLegend();
      }

      function toggleChapters() {
        global["node"].style("fill", global["colorChapters"]);

        colorMapping = global["colorChapters"];
        toggleLegend();
      }

      $(document).ready(function () {
        // scroll to where the graph will be created
        setTimeout(centerViewport, 100);

        disableContextMenu();

        createControls("<?php print $_GET["tag"]; ?>", "force");
        $("div#controls").append("<ul>");
        $("div#controls ul").append("<li><a href='javascript:void(0)' onclick='toggleHeatMax();'>view as heatmap (depth)</a><br>");
        $("div#controls ul").append("<li><a href='javascript:void(0)' onclick='toggleHeatMin();'>view as heatmap (height)</a><br>");
        $("div#controls ul").append("<li><a href='javascript:void(0)' onclick='toggleType();'>view types</a>");
        //$("div#controls ul").append("<li><a href='javascript:void(0)' onclick='toggleChapters();'>view chapters</a>");
        $("div#controls").append("</ul>");

        $("div#information").css("top", "135px");
      });
    </script>
  </head>
  <body>
    <script type="text/javascript">
      var width = <?php print $size; ?>,
        height = <?php print $size; ?>;
      
      var force = d3.layout.force()
        .charge(-500)
        .linkDistance(10)
        .gravity(.5)
        .size([width, height]);

      d3.select("body").append("div").attr("id", "graph");
      
      var svg = d3.select("div#graph").append("svg")
        .attr("width", width)
        .attr("height", height)

      var global = Array(); // this catches some things that need to be available globally
      
      result = d3.json("<?php print href("data/tag/" . $_GET["tag"] . "/graph/force"); ?>", function(error, graph) {
        var heatMaxSize = 0; // this corresponds to the depth variable, and starts with 0 at the root node
        var heatMinSize = 0; // this corresponds to the size variable, and starts with 0 at a leaf node, taking the maximum over the children + 1 as the value for a parent

        for (var i = 0; i < graph.nodes.length; i++) {
          heatMaxSize = Math.max(heatMaxSize, graph.nodes[i].depth);
          heatMinSize = Math.max(heatMinSize, graph.nodes[i].size);
        }
        // heat scales
        var heatMapMax = d3.scale.linear()
          .domain([0, heatMaxSize / 2.0, 3.0 * heatMaxSize / 4.0, heatMaxSize])
          .range(["red", "yellow", "green", "blue"]);
        var heatMapMin = d3.scale.linear()
          .domain([0, heatMaxSize / 2.0, 3.0 * heatMinSize / 4.0, heatMinSize])
          .range(["red", "yellow", "green", "blue"]);


        var chapters = {};
        for (var i = 0; i < graph.nodes.length; i++) 
          chapters[graph.nodes[i].file] = true;
        var i = 0;
        for (chapter in chapters)
          chapters[chapter] = i++;

        var chapterMap = d3.scale.linear().domain([0, Object.keys(chapters).length]).range(["green", "yellow"]);

        function colorHeatMax(node) { return heatMapMax(node.depth); }
        function colorHeatMin(node) { return heatMapMin(heatMinSize - node.size); }
        function colorType(node) { return typeMap(node.type); }
        function colorChapters(node) { return chapterMap(chapters[node.file]); }

        global["colorHeatMax"] = colorHeatMax;
        global["colorHeatMin"] = colorHeatMin;
        global["colorType"] = colorType;
        global["colorChapters"] = colorChapters;

        force
          .nodes(graph.nodes) 
          .links(graph.links)
          .start();

        var link = svg.selectAll(".link")
          .data(graph.links)
          .enter().append("line")
          .attr("class", "link")

        var node = svg.selectAll(".node")
          .data(graph.nodes)
          .enter().append("circle")
          .attr("class", namedClass)
          .attr("id", function(d) { if (d.depth == 0) { return "root"; } })
          .attr("r", function(d) { return 4 * Math.pow(parseInt(d.size) + 1, 1 / 3); })
          .style("fill", function(d) { colorMapping = colorHeatMax; return colorHeatMax(d); })
          .on("mouseover", displayTagInformation)
          .on("mouseout", hideTagInformation)
          .on("click", function(node) { openTag(node, "force"); })
          .on("contextmenu", function(node) { openTagNew(node, "force"); })
          .call(force.drag);

        global["node"] = node;

        force.on("tick", function() {
          link
            .attr("x1", function(d) { return d.source.x; })
            .attr("y1", function(d) { return d.source.y; })
            .attr("x2", function(d) { return d.target.x; })
            .attr("y2", function(d) { return d.target.y; });
           
          node
            .attr("cx", function(d) { return d.x; })
            .attr("cy", function(d) { return d.y; });
        });

        
        // add legend for the type coloring
        var types = {};
        for (var i = 0; i < graph.nodes.length; i++) 
          types[graph.nodes[i].type] = true;

        // add legend for type coloring
        typeLegend(types);

        // add legend for the heatMax coloring
        $("body").append("<div class='legend' id='legendHeatMax'></div>");
        $("div#legendHeatMax").append("Legend for the heat mapping (depth from root)<br>");
        $("div#legendHeatMax").append("root node&nbsp;&nbsp;");
        for (var i = 0; i <= heatMaxSize; i++) 
          $("<svg height='10' width='10'><circle cx='5' cy='5' r='5' fill='" + heatMapMax(i) + "'/></svg>").appendTo($("div#legendHeatMax"));
        $("div#legendHeatMax").append("&nbsp;&nbsp;children");

        $("div#legendHeatMax").append("<br><br>");
        $("div#legendHeatMax").append(bordersLegend());

        // add legend for the heatMin coloring
        $("body").append("<div class='legend' id='legendHeatMin'></div>");
        $("div#legendHeatMin").append("Legend for the heat mapping (height from leaf)<br>");
        $("div#legendHeatMin").append("root node&nbsp;&nbsp;");
        for (var i = 0; i <= heatMinSize; i++) 
          $("<svg height='10' width='10'><circle cx='5' cy='5' r='5' fill='" + heatMapMin(i) + "'/></svg>").appendTo($("div#legendHeatMin"));
        $("div#legendHeatMin").append("&nbsp;&nbsp;children");

        $("div#legendHeatMin").append("<br><br>");
        $("div#legendHeatMin").append(bordersLegend());

        // add legend for the chapter coloring
        $("body").append("<div class='legend' id='legendChapters'></div>");
        $("div#legendChapters").append("<p>Legend for the chapter mapping</p>");
        $("div#legendChapters").append("<ul>");
        for (chapter in chapters) {
          $("<li><svg height='10' width='10'><circle cx='5' cy='5' r='5' fill='" + chapterMap(chapters[chapter]) + "'/></svg>").append(" " + chapter).appendTo($("div#legendChapters ul"));
        }
        // TODO it would be awesome if the chapters legend had mouseOvers to indicate which results are in which chapter: making all the other chapters slightly lighter for instance

        toggleLegend();
      });
    </script>
  </body>
</html>
