#!/usr/bin/env python3

import http.cookiejar
import urllib.request, urllib.error, urllib.parse
from html.parser import HTMLParser
import getpass, re, json, time

# Took this class from 'habr143972_vk_auth_2to3processed.py' and improved a bit
class FormParser(HTMLParser):
    def __init__(self):
        HTMLParser.__init__(self)
        self.url = None
        self.params = {}
        self.in_form = False
        self.form_parsed = False
        self.method = "GET"

    def handle_starttag(self, tag, attrs):
        tag = tag.lower()
        if tag == "form":
            if self.form_parsed:
                raise RuntimeError("Second form on page")
            if self.in_form:
                raise RuntimeError("Already in form")
            self.in_form = True 
        if not self.in_form:
            return
        attrs = dict((name.lower(), value) for name, value in attrs)
        if tag == "form":
            self.url = attrs["action"] 
            if "method" in attrs:
                self.method = attrs["method"]
        elif tag == "input" and "type" in attrs and "name" in attrs:
            if attrs["type"] in ["hidden", "text", "password", "email", "checkbox"]:
                self.params[attrs["name"]] = attrs["value"] if "value" in attrs else ""

    def handle_endtag(self, tag):
        tag = tag.lower()
        if tag == "form":
            if not self.in_form:
                raise RuntimeError("Unexpected end of <form>")
            self.in_form = False
            self.form_parsed = True

class Logger():
    def __init__(self, fileName):
        self.logFile = open(fileName, "a")
        
    def addToLog(self, info, end = "\n"):
        t = time.strftime("%d.%m.%Y %H:%M:%S: ")
        print(t + info, end = end)
        self.logFile.write(t + info + end)
        self.logFile.flush()

    def addToLogWithNoTimestamp(self, info, end = "\n"):
        print(info, end = end)
        self.logFile.write(info + end)
        self.logFile.flush()

if __name__ == "__main__":
    email = ""
    password = ""

    logger = Logger("friends_positions_dumper.log")

    while True:
        logger.addToLog("Getting login page:", end = " ")
        opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), urllib.request.HTTPRedirectHandler())
        response = opener.open("https://accounts.google.com/ServiceLogin?service=friendview&continue=http://www.google.com/latitude&followup=http://www.google.com/latitude")
        doc = response.read().decode('utf-8')
        parser = FormParser()
        parser.feed(doc)
        parser.close()
        if not parser.form_parsed or parser.url is None or "Email" not in parser.params or "Passwd" not in parser.params:
            logger.addToLogWithNoTimestamp("FATAL FAIL [unexisting or invalid form]")
            raise RuntimeError("Something wrong")
        logger.addToLogWithNoTimestamp("DONE")

        if email == "" and password == "":
            email = input("Email: ")
            password = getpass.getpass()

        logger.addToLog("Logging in:", end = " ")
        parser.params["Email"] = email
        parser.params["Passwd"] = password
        if parser.method.upper() == "POST":
            response = opener.open(parser.url, urllib.parse.urlencode(parser.params).encode('utf-8'))
        else:
            logger.addToLogWithNoTimestamp("FATAL FAIL [unknown method]")
            raise NotImplementedError("Method '%s'" % parser.method)
        doc = response.read().decode('utf-8')
        if response.geturl() == "https://accounts.google.com/ServiceLoginAuth":
            logger.addToLogWithNoTimestamp("FATAL FAIL [incorrect email or password]")
            raise RuntimeError("Incorrect email or password")
        logger.addToLogWithNoTimestamp("DONE")

        logger.addToLog("Getting latitude page and parsing XsrfToken:", end = " ")
        response = opener.open("https://latitude.google.com/latitude/b/0")
        doc = response.read().decode('utf-8')
        XsrfTokenMatch = re.search(r'window\.LatitudeServerConstants\[\s*\'XsrfToken\'\s*\]\s*=\s*\'([^\']+)\'\s*;', doc)
        if XsrfTokenMatch == None:
            logger.addToLogWithNoTimestamp("FATAL FAIL [can\'t get XsrfToken]")
            raise RuntimeError("Can\'t get XsrfToken!")
        XsrfToken = XsrfTokenMatch.group(1)
        logger.addToLogWithNoTimestamp("DONE")
        
        jsonGettingOK = True

        while jsonGettingOK:
            logger.addToLog("Getting friends info and coordinates json dump:", end = " ")
            response = opener.open(urllib.request.Request("https://latitude.google.com/latitude/b/0/apps/pvjson?t=4", "[null,null,null,null,null,true,null,[null,null,null,null,null,null,null,2]]".encode('utf-8'), {"X-ManualHeader": XsrfToken}))
            doc = response.read().decode('utf-8')
            try:
                friendsInfo = json.loads(doc)
                logger.addToLogWithNoTimestamp("DONE")
                
                # save coordinates and etc
                
                time.sleep(10)
            except ValueError:
                jsonGettingOK = False
                parser = FormParser()
                parser.feed(doc)
                parser.close()
                if not parser.form_parsed or parser.url is None or "Email" not in parser.params or "Passwd" not in parser.params:
                    logger.addToLogWithNoTimestamp("FAIL [unrecognized answer]")
                else:
                    logger.addToLogWithNoTimestamp("FAIL [relogin page is the answer]")
