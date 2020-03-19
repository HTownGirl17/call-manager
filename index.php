<?php
    session_start();

    date_default_timezone_set("America/Chicago");

    $maxlines=6;    #how many phone lines - default 6
    $svar='calls';  #session variable name

    /* create a new session variable if it does not already exist */
    if( !isset( $_SESSION[ $svar ] ) )$_SESSION[ $svar ]=array();

    /* Process ajax POST requests */
    if( $_SERVER['REQUEST_METHOD']=='POST' && !empty( $_POST ) ){
        ob_clean();

        $cmd=filter_input( INPUT_POST, 'cmd', FILTER_SANITIZE_STRING );
        $line=filter_input( INPUT_POST, 'line', FILTER_SANITIZE_NUMBER_INT );

        if( $cmd ){
            switch( $cmd ){
                case 'poll':
                    $json=json_encode( $_SESSION[ $svar ] );
                break;
                case 'add-caller':
                    $_POST['time']=date('h:i:s');
                    $_SESSION[ $svar ][ $line ]=$_POST ;
                    $json=json_encode( $_SESSION[ $svar ] );
                break;
                case 'delete':
                    if( $line && array_key_exists( $line,$_SESSION[ $svar ] ) ) unset( $_SESSION[ $svar ][ $line ] );
                    $json=json_encode( $_SESSION[ $svar ] );
                break;
            }
        }
        header('Content-Type: application/json');
        exit( $json );
    }
?>
<!doctype html>
<html>
    <head>
        <meta charset='utf-8' />
        <title>Call Manager</title>
        <style>
            html, html *{font-family:calibri,verdana,arial;font-size:1rem;box-sizing:border-box;}
            body{
                background-image: url("radio-microphone.jpg"); background-repeat: no-repeat; background-position: center;
                    background-size: 30%;
            }
            h1 {color: red;}
            #container{width:95%;float:none;margin:0 auto;box-sizing:border-box;}
            #lhs{color: #f71a02; width:calc(20% - 2rem );float:left;height:80vh;}
            #rhs{width:calc(80% - 2rem );float:right;height:80vh;}
            #lhs,#rhs{display:block;clear:none;box-sizing:border-box;margin:0 1rem}
            input[type='button']{float:none;display:inline-block;margin:1rem auto;}
            input[type='text'],select{width:100%;padding:0.5rem;}
            table{width:100%;}
            td{text-align:center;}
            tr td:not([colspan]):first-of-type{text-align:left;}
            h1{font-size:2rem;text-align:center;}
            h2{font-size:1.25rem;text-align:center;}
            pre{clear:both;}
            ul,li{ display:block; width:100%;float:left;}
            li:nth-of-type(even){color: #0079c1; background:whitesmoke;}
            li:nth-of-type(odd){color: #fff; background: #0632BC;}
            option[disabled]{background:rgba(255,0,0,0.25)}
            li{padding:0.25rem 1rem;}
            li div span{font-weight:bold;margin:0 0 0 2rem;}
            li div input{ background-color:red; color:#fff;float:right!important;clear:none; }
        </style>
        <script>

            var _int;
            var _poll=2.5;

            function ajax(m,u,p,c,o){
                /*
                    Utility function for basic Ajax requests
                    m = method ~ GET or POST only
                    u = url ~ the script or resource to which the request will be sent
                    p = parameters ~ an object literal of parameters to send
                    c = callback ~ asynchronous callback function that processes the response
                    o = options ~ object literal of options which are also passed to callback

                */
                var xhr=new XMLHttpRequest();
                xhr.onreadystatechange=function(){
                    if( xhr.readyState==4 && xhr.status==200 )c.call( this, xhr.response, o, xhr.getAllResponseHeaders() );
                };
                if( o.hasOwnProperty('formdata') && o.formdata===true && m.toLowerCase()=='post' ){
                    /* send formdata object "as-is" ~ set p = FormData */
                } else {
                    var params=[];
                    for( var n in p )params.push( n+'='+p[ n ] );
                    switch( m.toLowerCase() ){
                        case 'post': p=params.join('&'); break;
                        case 'get': u+='?'+params.join('&'); p=null; break;
                    }
                }
                xhr.open( m.toUpperCase(), u, true );
                if( !o.hasOwnProperty('formdata') ) xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
                if( o && Object.keys( o ).length > 0 && o.hasOwnProperty('headers') ){
                    for( var h in o.headers )xhr.setRequestHeader( h, o.headers[ h ] );
                }
                xhr.send( p );
            }

            function createNode( t, a, p ) {
                try{
                    /*
                        utility function to simplify creation of new DOM nodes
                        t = type ~ node type or tag name of node
                        a = attributes ~ object literal of attributes to add to node
                        p = parent ~ the DOM node to which the new node will be added
                    */
                    var el = ( typeof( t )=='undefined' || t==null ) ? document.createElement( 'div' ) : document.createElement( t );
                    for( var x in a ) if( a.hasOwnProperty( x ) && x!=='innerHTML' ) el.setAttribute( x, a[ x ] );
                    if( a.hasOwnProperty('innerHTML') ) el.innerHTML=a.innerHTML;
                    if( p!=null ) typeof( p )=='object' ? p.appendChild( el ) : document.getElementById( p ).appendChild( el );
                    return el;
                }catch(err){
                    console.warn('createNode: %s, %o, %o',t,a,p);
                }
            }


            function bindEvents( event ){
                /* get references to dom nodes */
                var form=document.getElementById('caller-info');
                var select=form.querySelector('select');
                var rhs=document.getElementById('rhs').querySelector('ul');

                /* utility functions */
                var _clearform=function(){
                    Array.prototype.slice.call( form.querySelectorAll('input[type="text"]') ).forEach(function(n){n.value=''});
                };
                var _disable=function(i){
                    select.querySelector('option[data-id="'+i+'"]').disabled=true;
                };
                var _enable=function(i){
                    select.querySelector('option[data-id="'+i+'"]').removeAttribute('disabled');
                };
                var _deletecaller=function(e){
                    _enable.call( this, this.dataset.id );
                    _setcaller.call( this, this.dataset.id, 'Line '+this.dataset.id )
                    this.parentNode.parentNode.parentNode.removeChild( this.parentNode.parentNode );
                };
                var _setcaller=function(i,name){
                    select.querySelector('option[data-id="'+i+'"]').innerHTML=name;
                }


                /* ajax config */
                var method='post';
                var url=location.href;
                var options={ formdata:true, node:rhs, clear:true };
                var callback=function(r,o,h){
                    /* clear text fields in the form */
                    if( o.clear===true ) _clearform.call( this );

                    var data=JSON.parse( r );
                    for( var n in data ){

                        var json=data[n];
                        var id='caller_'+json.line;
                        /*
                            change attributes of select menu
                            and change the displayed text of 
                            selected option to indicate the line
                            is busy
                        */
                        _enable.call( this, json.line );
                        _disable.call( this, json.line );
                        _setcaller.call( this, json.line, 'Line '+json.line+' - '+json.name );


                        /*
                            if there is NOT a node with id ( as above )
                            then create a new `li` node with child content
                            including the `delete` button to which a new
                            `onclick` event listener is added.
                        */
                        if( !document.getElementById( id ) ){
                            var li=createNode( 'li', { id:id }, o.node );
                            var div=createNode( null,{ 'data-id':json.line, innerHTML:'<span>Line:</span> '+json.line+' <span>Name:</span> '+json.name+' <span>Town:</span> '+json.town+' <span>Topic:</span> '+json.topic+' <span>Waiting since: </span>' + json.time },li);
                            var bttn=createNode( 'input', { type:'button',value:'Delete','data-id':json.line }, div );
                                bttn.onclick=function( event ){
                                    ajax.call( this, method, url, { cmd:'delete', line:this.dataset.id }, _deletecaller.bind( this ), { node:rhs } );
                                }.bind( bttn );
                        }
                    }
                    
                    /*
                        If there are no child nodes (`li`) to the
                        `ul` - stop polling
                    */
                    if( o.node.childNodes.length==0 ){
                        clearInterval( _int );
                        _int=Number.NaN;
                        console.info('stop polling...');
                    }
                };


                var _beginpolling=function(){
                    if( isNaN( _int ) ){
                        console.info('start polling...');
                        _int=setInterval(function(){
                            ajax.call( this, method, url, { cmd:'poll' }, callback, { node:rhs, clear:false } );
                        }, 1000 * _poll );
                    }
                };



                /* Add caller - event listener assigned to `Submit Caller` button */
                var evtSubmitCaller=function( event ){
                    /*
                        rather than manually collecting form field parameters
                        and processing into a payload to send in the request, 
                        use the `FormData` object ~ adding ( append ) a custom
                        parameter `cmd`
                    */
                    var fd=new FormData( form );
                        fd.append('cmd','add-caller');
                    ajax.call( this, method, url, fd, callback, options );
                    _beginpolling.call( this );
                };
                form.querySelector('input[type="button"]').onclick=evtSubmitCaller;




                /* Poll every n seconds */
                _beginpolling.call( this );

            }
            document.addEventListener( 'DOMContentLoaded', bindEvents, false );
        </script>
    </head>
    <body>
        <h1>Radio Call Manager</h1>
        <div id='container'>
            <div id='lhs' data-id='callerInfo'>
                <form id='caller-info' method='post'>
                    <h2>Add Caller</h2>
                    <table>
                        <tbody>
                            <tr>
                                <td>Line</td>
                                <td>
                                    <select name='line'>
                                    <?php
                                        for( $i=1; $i <= $maxlines; $i++ ){
                                            $caller = !empty( $_SESSION[ $svar ] ) && array_key_exists( $i, $_SESSION[ $svar ] ) ? 'Line '.$i.' - '.$_SESSION[ $svar ][ $i ]['name'] : "Line $i";
                                            $disabled = !empty( $_SESSION[ $svar ] ) && array_key_exists( $i, $_SESSION[ $svar ] ) ? 'disabled=true' : '';
                                            echo "<option data-id='$i' value='$i' $disabled>$caller";
                                        }
                                    ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>Name</td>
                                <td> 
                                    <input type='text' name='name' />
                                </td>
                            </tr>
                            <tr>
                                <td>Town</td>
                                <td>
                                    <input type='text' name='town' />
                                </td>
                            </tr>
                            <tr>
                                <td>Topic</td>
                                <td>
                                    <input type='text' name='topic' />
                                </td>
                            </tr>
                            <tr>
                                <td colspan=2>
                                    <input type='button' value='Submit Caller' />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </form>
            </div>
            <div id='rhs' data-id='submittedInfo'>
                <ul></ul><!-- will be populated by ajax callback -->
            </div>
        </div>
    </body>
</html>
