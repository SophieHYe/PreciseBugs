function reducer(result, arg)
{
  arg = arg.split('=')

  // Get key node
  const keypath = arg.shift().split('.')

  let key = keypath.shift()
  let node = result

  while(keypath.length)
  {
    node[key] = node[key] || {}
    node = node[key]

    key = keypath.shift()
  }

  // Get value
  let val = true
  if(arg.length)
  {
    val = arg.join('=').split(',')
    if(val.length === 1) val = val[0]
  }

  // Store value
  node[key] = val

  return result
}


/**
 * This function takes the `cmdline` from `/proc/cmdline` **showed below in
 * the example** and splits it into key/value pairs
 * @access private
 * @param  {String} cmdline This string contains information about the
 *                          initrd and the root partition
 * @return {Object}         It returns a object containing key/value pairs
 *                          if there is no value for the key then its just true.
 *                          **For more Information, look at the example**
 * @example
 *   var cmdline1 = 'initrd=/initramfs-linux.img root=PARTUUID=someuuidhere\n'
 *   var cmdline2 = 'somevar root=PARTUUID=someuuidhere\n'
 *
 * 	 var res1 = linuxCmdline(cmdline1)
 * 	 var res2 = linuxCmdline(cmdline2)
 * 	 console.log(res1)
 * 	 //-> { initrd: '/initramfs-linux.img',root: 'PARTUUID=someuuidhere' }
 * 	 console.log(res2)
 * 	 //-> { somevar: true, root: 'PARTUUID=someuuidhere' }
 */
function linuxCmdline(cmdline)
{
  return cmdline.trim().split(' ').reduce(reducer, {})
}


module.exports = linuxCmdline
