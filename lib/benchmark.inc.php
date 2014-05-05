<?php
	function paymill_shutdownBenchmark(){
		return paymill_doBenchmark(false,'shutdown',true);
	}
	
	function paymill_doBenchmark($clear = false,$feature,$shutdown=false){
		if(function_exists('getrusage')){
			if($shutdown){
				if(function_exists('current_user_can') && function_exists('wp_get_current_user') && current_user_can('administrator')){
					$userSum	= 0;
					$systemSum	= 0;
					$sum		= 0;
					echo '<table id="paymillBenchmark" style="border-spacing: 10px;border-collapse: separate;">';
					echo '<tr><th><strong>Feature</strong></th><th><strong>User Time</strong></th><th><strong>System Time</strong></th><th><strong>Sum</strong></th></tr>';
					foreach($GLOBALS['paymillBenchmark'] as $feature => $data){
						echo '<tr><td>'.$feature.'</td><td>'.$data['userTime'].'</td><td>'.$data['systemTime'].'</td><td>'.$data['sumTime'].'</td></tr>';
						$userSum	= $userSum + $data['userTime'];
						$systemSum	= $systemSum + $data['systemTime'];
						$sum		= $sum + $data['sumTime'];
					}
					echo '<tr><td><strong>Sum</strong></th><td><strong>'.$userSum.'</strong></td><td><strong>'.$systemSum.'</strong></td><td><strong>'.$sum.'</strong></td></tr>';
					echo '</table>';
					
					global $wpdb;
					echo "<pre>";
					var_dump($wpdb->queries);
					echo "</pre>";
				}
			}else{
				static $stime;
				static $utime;
				static $sumtime;
				$ru = getrusage();
				$currentUTime		= $ru['ru_utime.tv_sec'] + round($ru['ru_utime.tv_usec']/1000000, 4);
				$currentSTime		= $ru['ru_stime.tv_sec'] + round($ru['ru_stime.tv_usec']/1000000, 4);
				$currentSumTime		= $currentUTime+$currentSTime;

				if(($stime || $utime) && !$clear){
					$benchmarkU											= $currentUTime - $utime;
					$benchmarkS											= $currentSTime - $stime;

					if(isset($GLOBALS['paymillBenchmark'][$feature]['userTime'])){
						$GLOBALS['paymillBenchmark'][$feature]['userTime']	= $GLOBALS['paymillBenchmark'][$feature]['userTime'] + $benchmarkU;
						$GLOBALS['paymillBenchmark'][$feature]['systemTime']	= $GLOBALS['paymillBenchmark'][$feature]['systemTime'] + $benchmarkS;
						$GLOBALS['paymillBenchmark'][$feature]['sumTime']		= $GLOBALS['paymillBenchmark'][$feature]['sumTime'] + $benchmarkU+$benchmarkS;
					}else{
						$GLOBALS['paymillBenchmark'][$feature]['userTime']	= $benchmarkU;
						$GLOBALS['paymillBenchmark'][$feature]['systemTime']	= $benchmarkS;
						$GLOBALS['paymillBenchmark'][$feature]['sumTime']		= $benchmarkU+$benchmarkS;
					}
				}
				$utime = $currentUTime;
				$stime = $currentSTime;
			}
		}
	}
?>