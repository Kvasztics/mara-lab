//-----------------------------------------------------------------------------
//  1.2 (2021.12.08.)
//  1.3 (2022.05.25 - & és ? karakterek kódolása az adatban encodeURIComponent() függvénnyel)
//  1.4 (2022.09.23 - formdata küldési típus a fájlfeltöltéshez)
//  1.5 (2026.08.30 - header küldés isAJAX?)
//  1.51 (2026.09.01) - request példányosítás
//-----------------------------------------------------------------------------

var wXHR = 
  {
      responseType   : '',
      //requestHeader  : {'Content-type':'application/x-www-form-urlencoded; charset=UTF-8'},
      requestHeader  : null,
      timeout        : 240000,
      async          : true,
      request        : null,
      onstart        : function() {},
      onsuccess      : function() {},	
      onerror        : function() {},
      onprogress     : function(event) {},
      uploadprogress : function(event) {},
//-----------------------------------------------------------------------------
//  
//-----------------------------------------------------------------------------
    config : function(options)
      {
        if (options.responseType) {this.responseType = options.responseType;}
        if (options.requestHeader) {this.requestHeader = options.requestHeader;}
        if (options.timeout) {this.timeout = options.timeout;}
        if (options.async) {this.async = options.async;}
      },
//-----------------------------------------------------------------------------
//  
//-----------------------------------------------------------------------------
    get : function(url, data, form)
      {
        this.send(url, 'GET', data, form);
      },
//-----------------------------------------------------------------------------
//  
//-----------------------------------------------------------------------------
    post : function(url, data, form)
      {
        this.send(url, 'POST', data, form);
      },
//-----------------------------------------------------------------------------
//  
//-----------------------------------------------------------------------------
    formdata : function(url, data, form)
      {
        this.send(url, 'FORMDATA', data, form);
      },            
//-----------------------------------------------------------------------------
//  
//-----------------------------------------------------------------------------
    settings : function()
      {
        var self = this;

        if (this.request)
          {
            this.request.responseType = this.responseType;

            this.request.setRequestHeader(
                'X-Requested-With',
                'XMLHttpRequest'
            );

            if (this.requestHeader)
              {
                for (const [key, value] of Object.entries(this.requestHeader)) 
                  {
                    this.request.setRequestHeader(`${key}`, `${value}`);
                  }
              }

            this.request.timeout = this.timeout;

            this.request.ontimeout = function()
              {
                self.onerror(self.request);
              };
          }
      },
//-----------------------------------------------------------------------------
//  
//-----------------------------------------------------------------------------
    send : function(url, method, data, form)
      {
        var self = this;
        this.request = new XMLHttpRequest();
        this.onstart(this.request);

        if (!form)
          {   
            if (method == 'FORMDATA')
              {
                this.request.open('post', url, this.async);
                var body = data;
              } 
            else
              {
                this.request.open(method, url, this.async); 
                var formData = this.getFormData(data);
                console.log(formData);
                var body = new URLSearchParams(formData);
              }     
          } 
        else
          {
            this.request.open(method, form.action, this.async);
            var body = new FormData(form);
          }

        this.settings();    
        this.request.send(body);        

        this.request.onload = function() 
          {
            // Session lejárt / nincs belépve
            if (self.request.status == 401)
              {
                try
                  {
                    var jsonData = JSON.parse(self.request.responseText);

                    if (jsonData.redirect)
                      {
                        window.location.href = jsonData.redirect;
                        return;
                      }
                  }
                catch (e)
                  {
                    console.error('Invalid login response:', e);
                  }
              }

            if (self.request.status == 200) 
              {
                self.onsuccess(self.request);
              } 
            else 
              {
                self.onerror(self.request);
              }
          };

        this.request.onerror = function() 
          {
            self.onerror(self.request);
          }; 

        this.request.onprogress = function(event) 
          {
            self.onprogress(self.request, event);
          };

        this.request.upload.onprogress = function(event) 
          {
            self.uploadprogress(self.request, event);
          };                                  
      },
//-----------------------------------------------------------------------------
//  
//-----------------------------------------------------------------------------      
    getFormData : function(data) 
      {
        var formData = new FormData();

        if (data)
          {
            Object.keys(data).forEach(key => {
              if (typeof data[key] !== 'object') 
                {
                  formData.append(key, data[key]);
                } 
              else 
                {
                  formData.append(key, JSON.stringify(data[key]));
                }
            })
          }  

        return formData;
      }                  
    }