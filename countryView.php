<?php
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
	//Get URL Prams
	$country = $_GET['country'];
	$geoId = $_GET['geoId'];

	//Connect to Postgres Database
	$dbh = pg_connect("host=localhost dbname=covid2 user=pi password=password");
	if(!$dbh) {
		die("Error:" . pg_last_error());
	}
?>

<!DOCTYPE html>
<html>
	<head>
		<?php
			//Custom title in Browser
			echo "<title>Corona Data for ". $country . "</title>";
		?>
		<meta charset="utf-8">
		<style>
			html,body {
				height: 100%;
				font: 400 16px 'Open Sans';
			}
			h1, h3 {
				text-shadow: 1px 1px 1px #fff;
			}
			#chartdiv {
				width: 50%;
	  			height: 50%;
			}
			.center {
				margin: auto;
				width: 50%;
				padding: 10px;
				text-align: center;
			}
			#linechartdiv {
				width: 95%;
				height: 500px;
			}
			h1 {
				text-decoration: underline;
			}
			.container {
				width: 80%;
				margin: auto;
				padding: 10px;
			}

			.btn, .btn-two {
				margin: 9px;
			}

			.btn.small, 
			.btn-two.small, 
			.btn-gradient.small, 
			.btn-effect.small {
  				padding: 8px 18px;  
 				font-size: 14px;
			}

			a[class*="btn"] {text-decoration: none;}
			input[class*="btn"], 
			button[class*="btn"] {border: 0;}

			.btn {
				position: relative;
				border: 0;
				padding: 15px 25px;
				display: inline-block;
				text-align: center;
				color: black;
			}
			.btn:active {
				top: 4px;	
			}

			.btn.green {box-shadow: 0px 4px 0px black;}

			.one{
				width: 25%;
				float: left;
			}
			.two {
				margin-left: 15%;
			}
		</style>
	
		<!-- Resources -->
		<script src="https://cdn.amcharts.com/lib/4/core.js"></script>
		<script src="https://cdn.amcharts.com/lib/4/maps.js"></script>
		<script src="https://cdn.amcharts.com/lib/4/geodata/worldLow.js"></script>
		<?php 
			// Custom import to get the map for the country
			echo '<script src="https://cdn.amcharts.com/lib/4/geodata/' . strtolower($country) . 'High.js"></script>'; 
		?>
		<script src="https://cdn.amcharts.com/lib/4/charts.js"></script>
		<script src="https://cdn.amcharts.com/lib/4/themes/animated.js"></script>
	
		<!-- Map code -->
		<script>
			am4core.ready(function() {
				//Create Chart
				var chart = am4core.create("chartdiv", am4maps.MapChart);
				<?php 
					//Custom geoData
					echo 'chart.geodata = am4geodata_' . strtolower($country) . 'High;';
				?>
				am4maps.projection = new am4maps.projections.Miller();


				var polygonSeries = chart.series.push(new am4maps.MapPolygonSeries());
				polygonSeries.useGeodata = true;
				polygonSeries.mapPolygons.template.events.on("hit", function(ev) {
							chart.zoomToMapObject(ev.target);
				});
				var polygonTemplate = polygonSeries.mapPolygons.template;
				polygonTemplate.tooltipText = "{name}";
				polygonTemplate.fill = chart.colors.getIndex(0);
				polygonTemplate.nonScalingStroke = true;
			});
		</script>
	<?php
		// Get Chart data
		$sql = "SELECT * FROM corona WHERE geoId = '" . $geoId . "';";
		$result = pg_query($dbh, $sql);
		
		$num_rows = pg_num_rows($result);
		$i = 1;
		$casesString = "";
		$deathString = "";
		//Dynamically fetch deaths and cases
		while($rows = pg_fetch_array($result)) {
			$deathString = $deathString . "{date: new Date('" . $rows[1] . "'), value:" . $rows[3] . "}";
			$casesString  = $casesString . "{date: new Date('" . $rows[1] . "'), value:" . $rows[2] . "}";
			if($i < $num_rows) {
				$deathString = $deathString . ",";
				$casesString = $casesString . ",";
			}
			$i++;
		}
	?>
	<!-- Linechart Code -->
	<script>
		am4core.ready(function() {
			am4core.useTheme(am4themes_animated);
			//Define important Charts things
			var chart = am4core.create("linechartdiv", am4charts.XYChart);
			chart.dateFormatter.dateFormat = "yyyy-MM-dd";

			//Create X Axis and make it a date
			var dateAxis = chart.xAxes.push(new am4charts.DateAxis());
			dateAxis.renderer.minGridDistance = 60;
			dateAxis.title.text = "[font-style:italic #B10DC9]Date[/]";
			dateAxis.title.fontSize = 25;
			dateAxis.title.fontWeight = "bold";
			//Not needed, the datatooltips say the date
			dateAxis.cursorTooltipEnabled = false;

			//Create Y Axis and make it values only
			var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
			valueAxis.title.text = "[font-style:italic #FF0000]Deaths[/] [font-style:italic] and [/] [#00FFFF font-style:italic]Cases[/]";
			valueAxis.title.fontSize = 25;
			valueAxis.title.fontWeight = "bold";
			//Same as date
			valueAxis.cursorTooltipEnabled = false;


			//Line for Deaths of the country
			var deathSeries = chart.series.push(new am4charts.LineSeries());
			deathSeries.dataFields.valueY = "value";
			deathSeries.dataFields.dateX = "date";
			deathSeries.data = [<?php echo $deathString; ?>];
			//Format date to show normal Text
			deathSeries.tooltipText  = "{value} Deaths on {date.formatDate('ddt MMMM yyyy')}";

			//Segment event behaviour
			var deathSegment = deathSeries.segments.template;
			deathSegment.interactionsEnabled = true;
			deathSegment.events.on("over", function(event) {
				processOver(event.target.parent.parent.parent);
			});
			deathSegment.events.on("out", function(event) {
				processOut(event.target.parent.parent.parent);
			});

			var deathHoverState = deathSegment.states.create("hover");
			deathHoverState.properties.strokeWidth = 3;

			var deathDimmed = deathSegment.states.create("dimmed");
			deathDimmed.properties.stroke = am4core.color("#dadada");


			//Cosmetics
			deathSeries.name = "Deaths";
			deathSeries.stroke = am4core.color("#ff0000");
			deathSeries.strokeWidth = 3;
			deathSeries.tensionX = 0.7;
			deathSeries.legendSettings.labelText = "[bold {color}]{name}[/]";
			deathSeries.legendSettings.itemValueText = "[bold]{valueY}[/bold]";
			deathSeries.tooltip.getFillFromObject = false;
			deathSeries.tooltip.background.fill = am4core.color("#ff0000");
			
		

			// Cases Line works the same as Death Line
			var casesSeries = chart.series.push(new am4charts.LineSeries());
			casesSeries.dataFields.valueY = "value";
			casesSeries.dataFields.dateX = "date";
			casesSeries.data = [<?php echo $casesString;?>];
			casesSeries.tooltipText = "{value} Cases on {date.formatDate('ddt MMMM yyyy')}";
			
			var casesSegment = casesSeries.segments.template;
			casesSegment.interactionsEnabled = true;
			casesSegment.events.on("over", function(event) {
				processOver(event.target.parent.parent.parent);
			});
			casesSegment.events.on("out", function(event) {
				processOut(event.target.parent.parent.parent);
			});

			var casesHoverState = casesSegment.states.create("hover");
			casesHoverState.properties.strokeWidth = 3;

			var casesDimmed = casesSegment.states.create("dimmed");
			casesDimmed.properties.stroke = am4core.color("#dadada");


			//Cosmetics
			casesSeries.name = "Cases"
			casesSeries.stroke = am4core.color("#00ffff");
			casesSeries.strokeWidth = 3;
			casesSeries.legendSettings.labelText = "[bold {color}]{name}[/]";
			casesSeries.legendSettings.itemValueText = "[bold]{valueY}[/bold]";
			casesSeries.tooltip.getFillFromObject = false;
			casesSeries.tooltip.background.fill = am4core.color("#00ffff");

			//Legend Processing
			chart.legend = new am4charts.Legend();
			chart.legend.position = "right";
			chart.legend.scrollable = false;
			chart.legend.itemContainers.template.events.on("over", function(event) {
				processOver(event.target.dataItem.dataContext);
			});
			chart.legend.itemContainers.template.events.on('out', function(event) {
				processOut(event.target.dataItem.dataContext);
			});

			function processOver(hoveredSeries) {
 	 			hoveredSeries.toFront();

  				hoveredSeries.segments.each(function(segment) {
    				segment.setState("hover");
  				});

  				chart.series.each(function(series) {
    				if (series != hoveredSeries) {
    	 				series.segments.each(function(segment) {
        					segment.setState("dimmed");
      					});
      					series.bulletsContainer.setState("dimmed");
    				}			
  				});
			}

			function processOut(hoveredSeries) {
 	 			chart.series.each(function(series) {
    				series.segments.each(function(segment) {
      					segment.setState("default");
    				});
    				series.bulletsContainer.setState("default");
  				});
			}		

			//Cursor
			chart.cursor = new am4charts.XYCursor();
			chart.cursor.xAxis = dateAxis;
			chart.cursor.lineY.disabled = true;;
			chart.cursor.behavior = "none";
		});
	</script>
	</head>
	<body>
		<div class="container">
			<a href=".." class="btn green small one">< Return to the World Map</a>
			<h1 class="center two"> Covid Data for <?php echo $_GET['country']; ?></h1>
		</div>
		<div id="linechartdiv" class="center"></div>
		<h3 class="center">Find out what <?php echo $_GET['country'];?> looks like:</h3>
		<div id="chartdiv" class="center"></div>
	</body>
</html>
