Popover = require './Popover'

module.exports =

##*
# Popover that is attached to an HTML element.
#
# NOTE: The reason we do not use Atom's native tooltip is because it is attached to an element, which caused strange
# problems such as tickets #107 and #72. This implementation uses the same CSS classes and transitions but handles the
# displaying manually as we don't want to attach/detach, we only want to temporarily display a popover on mouseover.
##
class AttachedPopover extends Popover
    ###*
     * Timeout ID, used for setting a timeout before displaying the popover.
    ###
    timeoutId: null

    ###*
     * The element to attach the popover to.
    ###
    elementToAttachTo: null

    ###*
     * Constructor.
     *
     * @param {HTMLElement} elementToAttachTo The element to show the popover over.
     * @param {Number}      delay             How long the mouse has to hover over the elment before the popover shows
     *                                        up (in miliiseconds).
    ###
    constructor: (@elementToAttachTo, delay = 500) ->
        super()

    ###*
     * Destructor.
    ###
    destructor: () ->
        if @timeoutId
            clearTimeout(@timeoutId)
            @timeoutId = null

        super()

    ###*
     * Shows the popover with the specified text.
     *
     * @param {Number} fadeInTime The amount of time to take to fade in the tooltip.
    ###
    show: (fadeInTime = 100) ->
        coordinates = @elementToAttachTo.getBoundingClientRect();

        centerOffset = ((coordinates.right - coordinates.left) / 2)

        x = coordinates.left - (@$(@getElement()).width() / 2) + centerOffset
        y = coordinates.bottom

        super(x, y, fadeInTime)

    ###*
     * Shows the popover with the specified text after the specified delay (in miliiseconds). Calling this method
     * multiple times will cancel previous show requests and restart.
     *
     * @param {Number} delay      The delay before the tooltip shows up (in milliseconds).
     * @param {Number} fadeInTime The amount of time to take to fade in the tooltip.
    ###
    showAfter: (delay, fadeInTime = 100) ->
        @timeoutId = setTimeout(() =>
            @show(fadeInTime)
        , delay)
