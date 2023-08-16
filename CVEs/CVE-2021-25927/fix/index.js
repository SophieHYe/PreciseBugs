const defaultDelimiter = '.'

const isDate = (obj) => {
  return obj instanceof Date
}

const flatten = (obj, delimiter) => {
  const result = {}
  const seperator = delimiter || defaultDelimiter

  if (typeof obj !== 'object' || isDate(obj)) return obj

  const flat = (original, stack, prev) => {
    if (!Object.values(original).length && prev) {
      result[prev] = original

      return original
    }

    Object.entries(original).forEach(([key, value]) => {
      const newKey = prev
        ? prev + seperator + key
        : key
      if (typeof value === 'object' && value !== null) {
        stack.forEach((s) => {
          if (value === s && !isDate(value)) {
            value = '[Circular]'
          }
        })
        stack.push(value)

        if (typeof value === 'object' && !isDate(value)) {
          return flat(value, stack, newKey)
        }
      }
      result[newKey] = value
    })
  }

  flat(obj, [obj])

  return result
}

const unflatten = (obj, delimiter) => {
  const result = {}
  const seperator = delimiter || defaultDelimiter
  const proto = ['__proto__', 'constructor', 'prototype']

  if (typeof obj !== 'object' || isDate(obj)) return obj

  const unflat = (original) => {
    Object.keys(original).forEach((key) => {
      const newKeys = key.split(seperator)
      newKeys.reduce((o, k, i) => {
        if (proto.includes(newKeys[i])) return o
        return o[k] || (o[k] = isNaN(Number(newKeys[i + 1])) ? (newKeys.length - 1 === i ? original[key] : {}) : [])
      }, result)
    })
  }

  unflat(obj)

  return result
}

module.exports = {
  flatten,
  unflatten
}
