#!/usr/bin/env node

process.chdir(__dirname);

const penthouse = require('penthouse');
const fs = require('fs');
const yargs = require('yargs/yargs')
const { hideBin } = require('yargs/helpers');
const { exit } = require('process');
const argv = yargs(hideBin(process.argv)).argv

var params = {
    url: null,
    cssString: '',
    forceInclude: [],
};
const keys = Object.keys(argv);

function includeSelector(s) {
    const p = s.substr(0, 1) === '/' ? new RegExp(s.substr(1,s.length-2)) : s;
    params.forceInclude.push(p);
}

keys.forEach(key => {
    switch(key) {
        case 'url':
            params.url = argv[key];
            break;
        case 'css':
            if ('string' === typeof argv[key]) {
                params.cssString += fs.readFileSync(argv[key]);
            }
            if (Array.isArray(argv[key])) {
                argv[key].forEach(p => {
                    params.cssString += fs.readFileSync(p);
                });
            }
            break;
        case 'forceInclude':
            if ('string' === typeof argv[key]) {
                includeSelector(argv[key]);
            }
            if (Array.isArray(argv[key])) {
                argv[key].forEach(p => {
                    includeSelector(p);
                });
            }
            break;
        case 'cssString':
            params.cssString += argv[key];
            break;
        default:
            params[key] = argv[key];
    }
});

if(!params.url) {
    console.error("URL not specified");
    exit(10);
}
if(!params.cssString.length) {
    console.error("Source CSS not specified");
    exit(11);
}
penthouse(params)
  .then(criticalCss => {
    process.stdout.write(criticalCss);
  })
