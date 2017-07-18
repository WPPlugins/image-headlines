=== Image Headlines ===
Tags: images, headlines, replacement
Contributors: coldforged

This plugin allows you to have images created automatically for your entry 
titles. In this way you can utilize non-standard fonts and get smoother 
rendering than would be possible with simple text headlines. New to this version 
is improved integration with the WordPress administration interface under 
WordPress 1.5 Strayhorn as well as genuine soft-shadows behind the text for that 
smooth, custom look the girls love. 

== Installation ==

IMPORTANT NOTE! As of version 1.4 of the WordPress Plugin Manager, TTF font 
files are not allowed elements of plugins. Hence, the bundled font is not 
installed correctly. You will need to download the tarball or ZIP file and 
install the font into the WordPress installation’s ‘wp-content/image-headlines’ 
directory. Sorry about that. 

Your best bet is to first install the WordPress Plugin Manager and then perform 
a One-Click installation from there. That’s as simple as it gets. Failing that, 
you’re welcome to download the files and install the image-headlines.php file in 
'wp-content/plugins' and the warp1.ttf file in a directory that you create 
called 'wp-content/image-headlines'. That should get everything where it needs 
to go. You’ll then need to visit the plugins page of the WordPress 
administration and activate the plugin. See, don’t you wish you’d just used the 
Plugin Manager? 

In order to have your titles turned into images, you have to change how you get 
your titles! Why? Well, if I went around changing every single invocation of the 
title into an image you’d have images in your RSS feeds and anywhere else you 
call “the_title()”. You don’t want that. Instead, you tell me which of your 
titles you want to be images. You do that by editing your template—for instance, 
your Main Template which generally controls how your home page will look—and 
search for the following text: 

'the_title();'

Shouldn’t be hard to find. Make certain that this is the one you want changed, 
it might appear elsewhere in the file. This is the one somewhere after the 
“while (have_posts()) : the_post()” stuff. You’ll change this text to look like 
this instead: 

'the_title('-image-');'

== Using other fonts ==

The font I’ve included is the lovely Warp 1 by Alex Gollner. If you’d like to 
use some other font you are more than welcome to do so. First, though, you need 
to get it on the server. So, find yourself a gorgeous TrueType font—preferably 
in Windows format if you have the choice—and stick it on your server. One of the 
easiest ways is to use the built-in WordPress Upload utility. You’ll have to 
allow ttf files to be uploaded which you can change in the miscellaneous tab of 
your WordPress Options, but once you do that it’s literally just a few clicks to 
install the font. Simply click the “Upload” tab in WordPress administration, 
browse to your font (note that Windows is finicky with the file dialog around 
fonts… you’ll have to right-click on your file and hit “Properties…” and copy 
the filename from the properties and paste it into your file name box in the 
“Open…” dialog) and upload it! The plugin automatically searches your configured 
upload directory as well as the wp-content/image-headlines directory for valid 
TrueType fonts and lists those in the menu. Experiment with those fonts! 

== Configuration ==

Following installation you’ll likely want to configure the appearance of your 
titles. Simply go to the Options page of your WordPress installation where 
you’ll see a new option cunningly called “Headlines”. Click it. 

If everything has gone well with the installation you should see a collection of 
options and a nifty preview image of what your current settings look like. Yeah! 
Note that if your preview image is showing (it should be an image with “The 
quick brown fox jumped over the lazy dog.” in red letters with a soft gray 
background shadow) you are good to go. You’re welcome to customize it however 
you wish but as far as the plugin is concerned it’s a happy camper. 

Let’s talk options.


= General Configuration =

Really if you’re up and running you have nothing much to do here. This just sets 
what directory the plugin will use to store the images it generates. You can 
change it if you wish. 

= Font and Colors = 

As you might imagine this section will have the greatest bearing on the 
appearance of your titles. You’ll see a menu containing the list of available 
fonts and entry boxes for controlling the font size and color as well as what 
the background looks like. Note that all colors you see on this page must be 
specified in HTML color format, so #123456 or #FF0000 or even the shorthand 
version like #CCC. Anything else will break in fantastic and undefined ways and 
I will not be pleased if you ask me why your images aren’t showing up and it 
turns out that you have GREEN in the color field. You’ve been warned! 

If you make your background transparent it will likely look better. Turn the 
option on and off to see the difference. 

You can have a background image displayed behind your text if you want. I don’t 
use it much but that’s your call. 

= Line Spacing =

This will control the formatting of your image a bit, especially as it pertains 
to long lines. 

The left padding simply tells the plugin to leave some blank space at the left 
edge before it starts drawing the text. This may be useful in the case where you 
have a background image you want to include. 

You can enable the line-splitting option so that really long titles get split 
into multiple lines before rendering. This is important on fixed-width 
blogs—like the default Kubrick template in WordPress, for instance—so that you 
don’t break the appearance if you happen to spout off in your titles like I do. 
Selecting this option will break the text into multiple lines if the rendered 
line would exceed the maximum line width you specify. 

The vertical space is the additional space you want between each line in the 
case that we break up lines. The bigger the number the larger the gap between 
them. 

The line indent is the additional space between the left border of the image and 
any subsequent lines in the case of a line break. 


= Shadows = 

You can turn shadows completely off if you so desire. In which case, simply turn 
off that “Enable shadow” checkbox. But where’s the fun in that? You have your 
choice of two shadow styles: soft-shadows and so-called “classic” shadows. 
Here’s the explanation of both: 

= Soft Shadows = 

Soft shadows look like the shadows that Adobe Photoshop generates for you. They 
are generated by drawing the text in the color that you specify in the shadow 
color parameter after first offsetting the text by the amounts that you specify 
in the vertical and horizontal offset parameters. Once that’s drawn the entire 
shadow image is blurred mathematically. If you care about the nitty-gritty 
details, it performs an approximation of a Gaussian blur using a “squares” 
convolution kernel horizontally and vertically across the image, with the size 
of the kernel being based on the “shadow spread” parameter given. If you don’t 
care about the nitty gritty, think about blurring your eyes and looking at the 
text: the amount you blur your eyes is controlled by the “shadow spread” 
parameter. A small spread means that the shadow will be pretty well defined. A 
larger spread will mean that the shadow is spread out more and more diffuse (as 
well as the color tends to fade as the spread increases). 

PLEASE NOTE that large shadows means many more calculations and many more 
calculations means it is slower to calculate the final image and making it 
slower means using CPU time on the server and using too much CPU time makes ISP 
admins cry. Once a particular image for a particular title is created it doesn’t 
have to be created again, so you don’t have to worry about _constantly_ 
performing this calculation. But, take it from me, if you have a large spread on 
a large text size it can take 20 seconds to calculate the shadow for it. And 
that’s 20 seconds of 99% CPU utilization on the server processor which can 
create problems with certain hosts. You’ve been warned again. 

= “Classic” Shadows =

Classic shadows are pretty simple. First, we draw shadow two in the color you 
specify, and we draw it 2 times the number of pixels you specify in the offset 
parameter down and to the right of where the final text will be drawn. Then we 
draw shadow one in the color you specify, and we draw it the number of pixels 
you specify in the offset parameter down and to the right. Then we draw the text 
in your font color right where you want it. The “exciting” example on this page 
is an example using this method. Using this technique has the advantage that 
it’s fast. Using this technique has the disadvantage that it’s ugly. Okay, not 
ugly, just not as elegant and stylish as the soft ones. Then again I’m biased… I 
wrote the soft shadows and Joel Bennett did the classic ones :) . Nevertheless, 
you can create some interesting effects with the classic shadows. 

== Can I use it for things other than titles? ==

Of course you can!

Glad you asked! You can stick this anywhere in your templates—for instance if 
you want your various category titles rendered—or even in your posts and pages 
like the one above. First, if you want to put these images in your posts like 
this—say for fancy dropcaps—you’ll need a very helpful plugin aside from the 
Image Headline plugin. Go and find the RunPHP plugin. Install it. Then, wherever 
you want your text to appear, put in a call to the following function (if in a 
page or post, be sure to enable the “eval() Content” option): 

'<?php echo ImageHeadline_render( 'Whatever text','formats' ); ?>'

Where the ‘formats’ string is a list of formats that you want to override 
separated by ampersands (&). Anything you don’t specify will be set exactly as 
it is for your entry titles. Each format will be in the form of 
‘format_name=value’ where ‘format_name’ is defined as follows: 

* font_file – Full path to the TrueType font you want to use.
* font_size – The size in points to draw the text.
* font_color – The color in HTML format either full (#FF0000) or brief (#F00).
* background_color – The color of the background that the image will be  
displayed over in the same format as above.
* shadow_color – If soft shadows are on—which follows the main setting in your 
Options page—this controls what color to draw the shadow.
* shadow_spread – If soft shadows are on this controls the spread in pixels.
* shadow_vertical_offset – Controls how many pixels down to draw the shadow.
* shadow_horizontal_offset – Controls how many pixels to the right to draw the  
shadow.
* left_padding – Number of pixels from the left edge to start drawing the text.
* max_width – The maximum width in pixels allowed before the text is broken into  
multiple lines.
* space_between_lines – The number of pixels between the bottom of the previous  
line and the top of the next line for multiline images.
* line_indent – The additional number of pixels to indent subsequent lines in  
multiline images.
* shadow_first_color – The color to draw the first of the “classic” shadows if  
soft-shadows are not enabled.
* shadow_second_color – The color to draw the second of the “classic” shadows  
if soft-shadows are not enabled.
* shadow_offset – If using “classic” shadows, the number of pixels separating  
the shadows from the main text and each other.
* background_image – An image to draw under the text in the background.

So, if I want to set the font color to red, the size to 20 points, and the  
shadow spread to 5 pixels the format string would be:

'font_color=#F00&font_size=20&shadow_spread=5'

== Screenshots ==

You are permitted to gape at the beauty.