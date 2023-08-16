

merge = ->
  mutate {}, arguments...

clone = (target) ->
  if Array.isArray target
    target.map (element) ->
      clone element
  else if target and typeof target is 'object'
    mutate {}, target
  else
    target

mutate = ->
  target = arguments[0]
  for i in [1 ... arguments.length]
    source = arguments[i]
    if is_object_literal source
      target = {} unless is_object_literal target
      for name of source
        continue if name is '__proto__'
        target[name] = mutate target[name], source[name]
    else if Array.isArray source
      target = for v in source
        mutate undefined, v
    else unless source is undefined
      target = source
  target

snake_case = (source, convert=true) ->
  target = {}
  if is_object_literal source
    u = if typeof convert is 'number' and convert > 0
    then convert - 1 else convert
    for name of source
      src = source[name]
      name = _snake_case(name) if convert
      target[name] = snake_case src, u
  else
    target = source
  target

compare = (el1, el2) ->
  if is_object_literal el1
    return false unless is_object_literal el2
    keys1 = Object.keys(el1).sort()
    keys2 = Object.keys(el2).sort()
    return false unless keys1.length is keys2.length
    for key, i in keys1
      return false unless key is keys2[i]
      return false unless compare el1[key], el2[key]
  else if Array.isArray el1
    return false unless Array.isArray el2
    return false if el1.length isnt el2.length
    for i in [0...el1.length]
      return false unless compare el1[i], el2[i]
  else
    return false unless el1 is el2
  true
    

_snake_case = (str) ->
  str.replace /([A-Z])/g, (_, match, index) ->
    '_' + match.toLowerCase()

is_object = (obj) ->
  obj and typeof obj is 'object' and not Array.isArray obj

is_object_literal = (obj) ->
  test = obj
  if typeof obj isnt 'object' or obj is null then false else
    return true if Object.getPrototypeOf(test) is null
    while not false
      break if Object.getPrototypeOf(test = Object.getPrototypeOf(test)) is null
    return Object.getPrototypeOf(obj) is test

export {clone, compare, is_object, is_object_literal, merge, mutate, snake_case}
