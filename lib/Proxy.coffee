fs            = require 'fs'
stream        = require 'stream'
child_process = require 'child_process'

module.exports =

##*
# Proxy that handles communicating with the PHP side.
##
class Proxy
    ###*
     * The config to use.
    ###
    config: null

    ###*
     * The name (without path or extension) of the database file to use.
    ###
    indexDatabaseName: null

    ###*
     * Constructor.
     *
     * @param {Config} config
    ###
    constructor: (@config) ->

    ###*
     * Prepares parameters for execution.
     *
     * @param {Array} parameters
     *
     * @return {Array}
    ###
    prepareParameters: (args) ->
        parameters = [
            '-d memory_limit=-1',
            __dirname + "/../php/src/Main.php"
        ]

        for a in args
            parameters.push(a)

        return parameters

    ###*
     * Performs an asynchronous request to the PHP side.
     *
     * @param {String}   command        The command to execute.
     * @param {Array}    parameters     The arguments to pass.
     * @param {Callback} streamCallback A method to invoke each time streaming data is received.
     * @param {String}   stdinData      The data to pass to STDIN.
     *
     * @return {Promise}
    ###
    performRequestAsync: (command, parameters, streamCallback = null, stdinData = null) ->
        return new Promise (resolve, reject) =>
            proc = child_process.spawn(command, parameters)

            buffer = ''
            errorBuffer = ''

            proc.stdout.on 'data', (data) =>
                buffer += data

            proc.on 'close', (code) =>
                if errorBuffer or not buffer or buffer.length == 0
                    @showUnexpectedOutputError(errorBuffer)
                    reject({rawOutput: buffer, message: "No output received from the PHP side!"})
                    return

                try
                    response = JSON.parse(buffer)

                catch error
                    @showUnexpectedOutputError(buffer)

                if not response or not response.success
                    reject({rawOutput: buffer, message: 'An unsuccessful status code was returned by the PHP side!'})
                    return

                resolve(response.result)

            if streamCallback
                proc.stderr.on 'data', (data) =>
                    streamCallback(data)

            else
                proc.stderr.on 'data', (data) =>
                    errorBuffer += data

            if stdinData?
                proc.stdin.write(stdinData, 'utf-8')
                proc.stdin.end()

    ###*
     * @param {String} rawOutput
    ###
    showUnexpectedOutputError: (rawOutput) ->
        atom.notifications.addError('php-integrator - Oops, something went wrong!', {
            dismissable : true
            detail      :
                "PHP sent back something unexpected. This is most likely an issue with your setup. If you're sure " +
                "this is a bug, feel free to report it on the bug tracker.\n \n→ " + rawOutput
        })

    ###*
     * Performs a request to the PHP side.
     *
     * @param {Array}    args           The arguments to pass.
     * @param {Callback} streamCallback A method to invoke each time streaming data is received.
     * @param {String}   stdinData      The data to pass to STDIN.
     *
     * @todo Support stdinData for synchronous requests as well.
     *
     * @return {Promise}
    ###
    performRequest: (args, streamCallback = null, stdinData = null) ->
        php = @config.get('phpCommand')
        parameters = @prepareParameters(args)

        return @performRequestAsync(php, parameters, streamCallback, stdinData)

    ###*
     * Retrieves a list of available classes.
     *
     * @return {Promise}
    ###
    getClassList: () ->
        return @performRequest(['--class-list', '--database=' + @getIndexDatabasePath()])

    ###*
     * Retrieves a list of available classes in the specified file.
     *
     * @param {String} file
     *
     * @return {Promise}
    ###
    getClassListForFile: (file) ->
        if not file
            throw new Error('No file passed!')

        return @performRequest(['--class-list', '--database=' + @getIndexDatabasePath(), '--file=' + file])

    ###*
     * Retrieves a list of available global constants.
     *
     * @return {Promise}
    ###
    getGlobalConstants: () ->
        return @performRequest(['--constants', '--database=' + @getIndexDatabasePath()])

    ###*
     * Retrieves a list of available global functions.
     *
     * @return {Promise}
    ###
    getGlobalFunctions: () ->
        return @performRequest(['--functions', '--database=' + @getIndexDatabasePath()])

    ###*
     * Retrieves a list of available members of the class (or interface, trait, ...) with the specified name.
     *
     * @param {String} className
     *
     * @return {Promise}
    ###
    getClassInfo: (className) ->
        if not className
            throw new Error('No class name passed!')

        return @performRequest(
            ['--class-info', '--database=' + @getIndexDatabasePath(), '--name=' + className]
        )

    ###*
     * Resolves a local type in the specified file, based on use statements and the namespace.
     *
     * @param {String}  file
     * @param {Number}  line The line the type is located at. The first line is 1, not 0.
     * @param {String}  type
     *
     * @return {Promise}
    ###
    resolveType: (file, line, type) ->
        throw new Error('No file passed!') if not file
        throw new Error('No line passed!') if not line
        throw new Error('No type passed!') if not type

        return @performRequest(
            ['--resolve-type', '--database=' + @getIndexDatabasePath(), '--file=' + file, '--line=' + line, '--type=' + type]
        )

    ###*
     * Localizes a type to the specified file, making it relative to local use statements, if possible. If not possible,
     * null is returned.
     *
     * @param {String}  file
     * @param {Number}  line The line the type is located at. The first line is 1, not 0.
     * @param {String}  type
     *
     * @return {Promise}
    ###
    localizeType: (file, line, type) ->
        throw new Error('No file passed!') if not file
        throw new Error('No line passed!') if not line
        throw new Error('No type passed!') if not type

        return @performRequest(
            ['--localize-type', '--database=' + @getIndexDatabasePath(), '--file=' + file, '--line=' + line, '--type=' + type]
        )

    ###*
     * Performs a semantic lint of the specified file.
     *
     * @param {String}      file
     * @param {String|null} source  The source code of the file to index. May be null if a directory is passed instead.
     * @param {Object}      options Additional options to set. Boolean properties noUnknownClasses,
     *                              noDocblockCorrectness and noUnusedUseStatements are supported.
     *
     * @return {Promise}
    ###
    semanticLint: (file, source, options = {}) ->
        throw new Error('No file passed!') if not file

        parameters = ['--semantic-lint', '--database=' + @getIndexDatabasePath(), '--file=' + file, '--stdin']

        if options.noUnknownClasses == true
            parameters.push('--no-unknown-classes')

        if options.noDocblockCorrectness == true
            parameters.push('--no-docblock-correctness')

        if options.noUnusedUseStatements == true
            parameters.push('--no-unused-use-statements')

        return @performRequest(
            parameters,
            null,
            source
        )

    ###*
     * Fetches all available variables at a specific location.
     *
     * @param {String|null} file   The path to the file to examine. May be null if the source parameter is passed.
     * @param {String|null} source The source code to search. May be null if a file is passed instead.
     * @param {Number}      offset The character offset into the file to examine.
     *
     * @return {Promise}
    ###
    getAvailableVariables: (file, source, offset) ->
        if not file? and not source?
            throw 'Either a path to a file or source code must be passed!'

        if file?
            parameter = '--file=' + file

        else
            parameter = '--stdin'

        return @performRequest(
            ['--available-variables', '--database=' + @getIndexDatabasePath(), parameter, '--offset=' + offset, '--charoffset'],
            null,
            source
        )

    ###*
     * Fetches the types of the specified variable at the specified location.
     *
     * @param {String}      name   The variable to fetch, including its leading dollar sign.
     * @param {String}      file   The path to the file to examine.
     * @param {String|null} source The source code to search. May be null if a file is passed instead.
     * @param {Number}      offset The character offset into the file to examine.
     *
     * @return {Promise}
    ###
    getVariableTypes: (name, file, source, offset) ->
        if not file?
            throw 'A path to a file must be passed!'

        parameters = ['--variable-types', '--database=' + @getIndexDatabasePath(), '--name=' + name, '--offset=' + offset, '--charoffset']

        if file?
            parameters.push('--file=' + file)

        if source?
            parameters.push('--stdin')

        return @performRequest(
            parameters,
            null,
            source
        )

    ###*
     * Deduces the resulting types of an expression based on its parts.
     *
     * @param {Array}       parts  One or more strings that are part of the expression, e.g. ['$this', 'foo()'].
     * @param {String}      file   The path to the file to examine.
     * @param {String|null} source The source code to search. May be null if a file is passed instead.
     * @param {Number}      offset The character offset into the file to examine.
     *
     * @return {Promise}
    ###
    deduceTypes: (parts, file, source, offset) ->
        if not file?
            throw 'A path to a file must be passed!'

        parameters = ['--deduce-types', '--database=' + @getIndexDatabasePath(), '--offset=' + offset, '--charoffset']

        if file?
            parameters.push('--file=' + file)

        if source?
            parameters.push('--stdin')

        for part in parts
            parameters.push('--part=' + part)

        return @performRequest(
            parameters,
            null,
            source
        )

    ###*
     * Retrieves the scope chain at the specified offset.
     *
     * The scope chain contains a list of objects that describe what kind of code is located at the specified location.
     * This information can be useful to determine how code is nested (for example, a method call that is inside a
     * method that is in turn inside a class).
     *
     * @param {String}      file       The path to the file to examine.
     * @param {String|null} source     The source code to search. May be null if a file is passed instead.
     * @param {Number}      offset     The character offset into the file to examine.
     * @param {bool}        asSelector If set, a single CSS selector-like string will be returned instead of a list of
     *                                 objects.
     *
     * @return {Promise}
    ###
    getScopeChain: (file, source, offset, asSelector) ->
        if not file? and not source?
            throw 'Either a path to a file or source code must be passed!'

        parameters = ['--scope-chain', '--database=' + @getIndexDatabasePath(), '--offset=' + offset, '--charoffset']

        if file?
            parameters.push('--file=' + file)

        if source?
            parameters.push('--stdin')

        if asSelector
            parameters.push('--as-selector')

        return @performRequest(
            parameters,
            null,
            source
        )

    ###*
     * Refreshes the specified file or folder. This method is asynchronous and will return immediately.
     *
     * @param {String}      path                   The full path to the file  or folder to refresh.
     * @param {String|null} source                 The source code of the file to index. May be null if a directory is
     *                                             passed instead.
     * @param {Callback}    progressStreamCallback A method to invoke each time progress streaming data is received.
     *
     * @return {Promise}
    ###
    reindex: (path, source, progressStreamCallback) ->
        if not path
            throw new Error('No filename passed!')

        progressStreamCallbackWrapper = null

        if progressStreamCallback?
            progressStreamCallbackWrapper = (output) =>
                # Sometimes we receive multiple lines in bulk, so we must ensure it remains split correctly.
                percentages = output.toString('ascii').split("\n")
                percentages.pop() # Ditch the empty value.

                for percentage in percentages
                    progressStreamCallback(percentage)

        parameters = ['--reindex', '--database=' + @getIndexDatabasePath(), '--source=' + path, '--stream-progress']

        if source?
            parameters.push('--stdin')

        return @performRequest(
            parameters,
            progressStreamCallbackWrapper,
            source
        )

    ###*
     * Sets the name (without path or extension) of the database file to use.
     *
     * @param {String} name
    ###
    setIndexDatabaseName: (name) ->
        @indexDatabaseName = name

    ###*
     * Retrieves the full path to the database file to use.
     *
     * @return {String}
    ###
    getIndexDatabasePath: () ->
        return @config.get('packagePath') + '/indexes/' + @indexDatabaseName + '.sqlite'
