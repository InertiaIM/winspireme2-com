(function($, undefined) {
    $.HomeSlideshow = function(options, element) {
        this.$el = $(element);
        this._init(options);
    };
    
    $.HomeSlideshow.defaults = {
        // options for the jmpress plugin.
        // you can add much more options here. Check http://shama.github.com/jmpress.js/
        jmpressOpts: {
            // set the viewport
            viewPort: {
                height: 362,
                width: 1040,
                maxScale: 1
            },
            fullscreen: false,
            hash: {
                use: false
            },
            mouse: {
                clickSelects: false
            },
            keyboard: {
                use: false
            },
            animation: {
                transitionDuration: '1s'
            }
        },
        // for this specific plugin we will have the following options:
        // shows/hides navigation dots/pages
        dots: true,
        // each step's bgcolor transition speed
        bgColorSpeed: '1s',
        // slideshow on / off
        autoplay: false,
        // time between transitions for the slideshow
        interval: 3500
    };
    
    $.HomeSlideshow.prototype = {
        _init: function(options) {
            
            this.options = $.extend(true, {}, $.HomeSlideshow.defaults, options);
            
            // each one of the slides
            this.$slides = $('.slide-wrapper').children('div');
            // total number of slides
            this.slidesCount = this.$slides.length;
            // step's backgrounds
            this.backgrounds = $.map(this.$slides, function(el, i) {
                return $(el).data('background');
            }).join(' ');
            // build the necessary structure to run jmpress
            this._layout();
            // initialize the jmpress plugin
            this._initImpress();
            // if support (function implemented in jmpress plugin)
            if(this.support) {
                
                // load some events
                this._loadEvents();
                // if autoplay is true start the slideshow
                if(this.options.autoplay) {
                    this._startSlideshow();
                }
            }
        },
        
        // adds the navigation options (dots) if set to true
        _layout: function() {
            // adds a specific class to each one of the steps
            this.$slides.each(function(i) {
                $(this).addClass('jmstep' + (i + 1));
            });
            
//            // wrap the slides. This wrapper will be the element on which we will call the jmpress plugin
//            this.$jmsWrapper	= this.$slides.wrapAll('<div class="home-wrapper"/>').parent();
            this.$jmsWrapper = $('.slide-wrapper');
//            
//            // transition speed for the wrapper bgcolor 
//            this.$jmsWrapper.css({
//                '-webkit-transition-duration': this.options.bgColorSpeed,
//                '-moz-transition-duration': this.options.bgColorSpeed,
//                '-ms-transition-duration': this.options.bgColorSpeed,
//                '-o-transition-duration': this.options.bgColorSpeed,
//                'transition-duration': this.options.bgColorSpeed
//            });
            
            // add navigation dots
            if(this.options.dots) {
                this.$dots = $('<nav class="nav-dots"></nav>');
                
                for(var i = this.slidesCount + 1; --i;) {
                    this.$dots.append((i === this.slidesCount) ? '<span class="nav-dots-current"/>' : '<span/>');
                }
                
                if(this.options.jmpressOpts.start) {
                    this.$start = this.$jmsWrapper.find(this.options.jmpressOpts.start), idxSelected = 0;
                    
                    (this.$start.length) ? idxSelected = this.$start.index() : this.options.jmpressOpts.start = null;
                    
                    this.$dots.children().removeClass('nav-dots-current').eq(idxSelected).addClass('nav-dots-current');
                }
                
                this.$el.append(this.$dots);
            }
        },
        
        // initialize the jmpress plugin
        _initImpress: function() {
            var _self = this;
            
            this.$jmsWrapper.jmpress(this.options.jmpressOpts);
            // check if supported (function from jmpress.js):
            // it adds the class not-suported to the wrapper
            this.support = !this.$jmsWrapper.hasClass('not-supported');
            
            // if not supported remove unnecessary elements
            if(!this.support) {
                if(this.$dots) {
                    this.$dots.remove();
                }
                
                return false;
            }
            
            // redefine the jmpress setActive method
            this.$jmsWrapper.jmpress('setActive', function(slide, eventData) {
                
                // change the pagination dot active class
                if(_self.options.dots) {
                    
                    // adds the current class to the current dot/page
                    _self.$dots
                        .children()
                        .removeClass('nav-dots-current')
                        .eq(slide.index())
                        .addClass('nav-dots-current');
                }
                
                // delete all current background classes
                this.removeClass(_self.backgrounds);
                // add background class
                this.addClass(slide.data('background'));
            });
            
            // add step's background class to the wrapper
            this.$jmsWrapper.addClass(this.$jmsWrapper.jmpress('active').data('background'));
        },
        
        // start slideshow if autoplay is true
        _startSlideshow: function() {
            var _self = this;
            
            this.slideshow = setTimeout(function() {
                _self.$jmsWrapper.jmpress('next');
                
                if(_self.options.autoplay) {
                    _self._startSlideshow();
                }
            }, this.options.interval);
        },
        
        // stops the slideshow
        _stopSlideshow: function() {
            if(this.options.autoplay) {
                clearTimeout(this.slideshow);
                this.options.autoplay = false;
            }
        },
        
        _loadEvents: function() {
            var _self = this;
            
            // navigation dots
            if(this.$dots) {
                this.$dots.children().on('click.homeslideshow', function(event) {
                    _self._stopSlideshow();
                    _self.$jmsWrapper.jmpress('goTo', '.jmstep' + ($(this).index() + 1));
                    
                    return false;
                });
            }
            
            // the touchend event is already defined in the jmpress plugin.
            // we just need to make sure the slideshow stops if the event is triggered
            this.$jmsWrapper.on('touchend.homeslideshow', function() {
                _self._stopSlideshow();
            });
        }
    };
    
    var logError = function(message) {
        if (this.console) {
            console.error(message);
        }
    };
    
    $.fn.homeslideshow = function(options) {
        if (typeof options === 'string') {
            var args = Array.prototype.slice.call(arguments, 1);
            
            this.each(function() {
                var instance = $.data(this, 'homeslideshow');
                
                if (!instance) {
                    logError('cannot call methods on homeslideshow prior to initialization; ' +
                    'attempted to call method "' + options + '"');
                    return;
                }
                
                if (!$.isFunction( instance[options]) || options.charAt(0) === '_') {
                    logError('no such method "' + options + '" for homeslideshow instance');
                    return;
                }
                
                instance[options].apply(instance, args);
            });
        }
        else {
            this.each(function() {
                var instance = $.data(this, 'homeslideshow');
                if (!instance) {
                    $.data(this, 'homeslideshow', new $.HomeSlideshow(options, this));
                }
            });
        }
        
        return this;
    };
})(jQuery);