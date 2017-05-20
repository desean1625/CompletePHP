import sublime, sublime_plugin
import subprocess
import json

class CompletePHPAutocomplete(sublime_plugin.EventListener):
    def on_query_completions(self, view, prefix, locations):
        if "PHP" not in view.settings().get('syntax'):
            return
        region = sublime.Region(int(locations[0]-3),int(locations[0]-1))
        chars = view.substr(region);
        if chars != "->":
            if chars != "::":            
                return []

        opts = []
        settings = sublime.load_settings('CompletePHP.sublime-settings')
        php_path = "php"
        php_settings = settings.get('php_path')
        if (php_settings != ""):
            php_path = php_settings


        cmd = []
        cmd.append(str(php_path))
        cmd.append(sublime.packages_path()+"/CompletePHP/reflect.php")
        cmd.append(view.file_name())
        platform = sublime.platform()

        stderr = ""
        stdout = ""
        try:
            if (platform == "windows"):
                startupinfo = subprocess.STARTUPINFO()
                startupinfo.dwFlags |= subprocess.STARTF_USESHOWWINDOW
                startupinfo.wShowWindow = subprocess.SW_HIDE
                p = subprocess.Popen(cmd, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, startupinfo=startupinfo, shell=False, creationflags=subprocess.SW_HIDE)
            else:
                p = subprocess.Popen(cmd, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            stdout, stderr = p.communicate()
        except Exception as e:
            stderr = str(e)
            print(stderr)

        if (not stderr and not stdout):
            stderr = "Error while gethering list of php transformations"

        if len(stderr) == 0 and len(stdout) > 0:
            text = stdout.decode('utf-8')
        if len(text) == 0:
            return [];
        data = json.loads(text)
        sugs = []
        for k in data.keys():
            
            if k.startswith(prefix):
                for c in data[k]:
                    sug = "%s()\t%s" % (k,c['class'])
                    replacementText = "%s(" % (k)
                    tab = 1
                    parts = []
                    for p in c['params']:
                        param = "\$${%s:%s}" % (tab,p)
                        parts.append(param)
                        tab += 1
                    replacementText += ",".join(parts)
                    replacementText += ");"
                    sugs.append((sug,replacementText))
        #sugs.append(next( v for k,v in data.items() if k.startswith(prefix)))
        #print(sugs)
        #sugs = [(x.attrib["data"],) * 2 for x in elements]
        #sugs = [("test()\tBluefile","test(\$${1:param},\$${2:param2})"),("test2()\tBlue","test2()")];
        return sugs

