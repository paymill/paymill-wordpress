/*

 * DC Tooltip - jQuery tooltip plugin
 * Copyright (c) 2011 Design Chemical
 *
 * Dual licensed under the MIT and GPL licenses:
 * 	http://www.opensource.org/licenses/mit-license.php
 * 	http://www.gnu.org/licenses/gpl.html
 *
 */

(function($){

	//define the new for the plugin ans how to call it
	$.fn.dcTooltip = function(options) {

		//set default options
		var defaults = {
			classWrapper	: 'tooltip',
			hoverDelay		: 300,
			speed       	: 'fast',
			distance		: 20,
			padLeft			: 0
		};

		//call in the default otions
		var options = $.extend(defaults, options);

		//act upon the element that is passed into the design
		return this.each(function(options){

			// 1. Get the text, create the tooltip and append
			var getText = $(this).attr('title');
			var $wrapper = '<div class="'+defaults.classWrapper+'"><div class="top"></div><div class="text">'+getText+'</div></div>';
			$(this).append($wrapper);
			// 2. Get the dimensions of the tooltip
			var $tooltip = $('.'+defaults.classWrapper,this);
			var widthP = $tooltip.width();
			// 3. Get the dimensions of the element
			var widthT = $(this).width();
			var heightT = $(this).height();
			// 4. Set margins based on element dimensions and distance for animation
			var marginTop = heightT - defaults.distance;
			var marginLeft = (widthP - widthT)/2;
			marginLeft = -marginLeft + defaults.padLeft;
			$tooltip.css({marginLeft: marginLeft+'px', bottom: marginTop+'px'});
			// 5. Set the element position to relative & tooltip opacity to 0
			$(this).css('position','relative');
			$tooltip.css('opacity',0);
			// 6. Remove the element's title text
			$(this).removeAttr('title');
			
			// Configuration settings for HoverIntent plugin
			var config = {
				sensitivity: 2, // number = sensitivity threshold (must be 1 or higher)
				interval: defaults.hoverDelay, // number = milliseconds for onMouseOver polling interval
				over: linkOver, // function = onMouseOver callback (REQUIRED)
				timeout: defaults.hoverDelay, // number = milliseconds delay before onMouseOut
				out: linkOut // function = onMouseOut callback (REQUIRED)
			};
			
			// Initialise HoverIntent
			$(this).hoverIntent(config);
			
			// Hover link over
			function linkOver(){

				$tooltip.show().css({
					bottom: marginTop+'px'
                }).animate({
                    bottom: defaults.distance+marginTop,
                 opacity: 1
                }, defaults.speed);
			}
			
			// Hover link over
			function linkOut(){
				
				$('.'+defaults.classWrapper,this).animate({
                    bottom: (defaults.distance*1.5)+marginTop,
                    opacity: 0
			    }, defaults.speed, function() {
					$(this).hide();
				});

			}
		});
	};
})(jQuery);