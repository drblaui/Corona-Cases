<?php	
	//Connect to Postgres Database
	$dbh = pg_connect("host=XXX.XXX.X.XXX dbname=covid_data user=XXXX password=XXXX");
	if(!$dbh) {
		die("Error:" . pg_last_error());
	}
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title> Learn more about Corona in the World</title>

		<style>
			html,body {
				/*Prevent Scrollbar from showing up on Desktop devices*/ 
				height: 85%;
			}
			#chartdiv {
				width: 100%;
	  			height: 100%;
			}
			.center {
				margin: auto;
				width: 50%;
				padding: 10px;
				text-align: center;
			}
			#legenddiv {
  				height: 50px;
			}
		</style>
	
		<!-- Resources -->
		<script src="https://cdn.amcharts.com/lib/4/core.js"></script>
   		<script src="https://cdn.amcharts.com/lib/4/maps.js"></script>
    	<script src="https://cdn.amcharts.com/lib/4/geodata/worldLow.js"></script>
		<script src="https://cdn.amcharts.com/lib/4/themes/animated.js"></script>
		
		<?php

			$sql = "SELECT geoId, popData2018 FROM country;";
			$result = pg_query($dbh, $sql);

			$includeArr = "";
			$data = "";
			$i = 1;
			$num_rows = pg_num_rows($result);
			//Create the datasets for the map
			while($rows = pg_fetch_array($result)) {
				$includeArr = $includeArr . '"' .  $rows[0] . '"';
				$data = $data . '{"id": "' . $rows[0] . '", "value": ' . $rows[1] . '}'; 
				if($i < $num_rows) {
					$includeArr = $includeArr . ',';
					$data = $data . ',';
				}
				$i++;
			}
		?>
	
		<!-- Chart code -->
		<script>
			am4core.ready(function() {
				// Create map instance
				var chart = am4core.create("chartdiv", am4maps.MapChart);
	
				// Set map definition
				chart.geodata = am4geodata_worldLow;
	
				// Set projection (Miller is simply the best)
				chart.projection = new am4maps.projections.Miller();

            	/* All countries we have no Data for */
            	var noData = chart.series.push(new am4maps.MapPolygonSeries());
				noData.name = "Countries without Data";
				//Set to relative Position on map
				noData.useGeodata = true;
				//Fuck Antarctica and also dont overlay our data countries
				noData.exclude = ["AQ", <?php echo $includeArr; ?>];
            	noData.mapPolygons.template.tooltipText = "{name}";
            	noData.mapPolygons.template.fill = am4core.color("#00ffff");
            	noData.fill = am4core.color("#00ffff");	
				
            	/* All countries we have Data for */
            	var infected = chart.series.push(new am4maps.MapPolygonSeries());
            	infected.name = "Countries with Data";
            	infected.useGeodata = true;
				infected.include = [<?php echo $includeArr; ?>];
				//Show Name and Population on hover
            	infected.mapPolygons.template.tooltipText = "Country: [bold]{name}[/]\n Population 2018: [bold]{value}";
            	infected.mapPolygons.template.fill = am4core.color("#ff0000");
				infected.fill = am4core.color("#ff0000");
				infected.data = [<?php echo $data; ?>];

				//Create the Legend in seperate Container, because it overlaps with the map
				var legendContainer = am4core.create("legenddiv", am4core.Container);
				legendContainer.width = am4core.percent(100);
				legendContainer.height = am4core.percent(100);
				//Assign legend to outer container
				chart.legend = new am4maps.Legend();
				chart.legend.parent = legendContainer;
				
				//Small map for beautiful looking or something
				chart.smallMap = new am4maps.SmallMap();
				//IMPORTANT. The small map would not have all countries otherwise
				chart.smallMap.series.push(noData);
				chart.smallMap.series.push(infected);

				//Behaviour for infected countries on click
				infected.mapPolygons.template.events.on("hit", function(ev) {
					//Zoom into it
					ev.target.series.chart.zoomToMapObject(ev.target);
					// "Smooth" transition into specific data for country, so it looks like we just zoomed in
					setTimeout(() => {
						//Relocate to URL
						window.location.href = "./countryView.php?geoId=" + ev.target.dataItem.dataContext.id + "&country=" + ev.target.dataItem.dataContext.name;
					}, 1500);
				});
			});
	</script>
	</head>
	<body>
		<h1 class="center"> Covid! </h1>
		<h5 class="center"> Click a red country to get more Data about it!</h5>
		<div id="chartdiv"></div>
		<div id="legenddiv"></div>
	</body>
</html>