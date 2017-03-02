
/*
* MIT License
*
* Copyright (c) 2017 Ng Chiang Lin
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in all
* copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
* SOFTWARE.
*/

/*
 * Simple multithreaded java client app to 
 * test rate limiting for a web application
 * 
 * Ng Chiang Lin
 * Feb 2017
 * 
 */

import java.io.BufferedReader;
import java.io.IOException;
import java.io.InputStreamReader;
import java.net.URL;

import javax.net.ssl.HttpsURLConnection;

public class WebClient extends Thread
{
    private String name;
    private String url;
    private long interval;
    private long starttime;
    private int allowed;
    private int disallowed;
    private int total;
    private int http200ok;
    private int httperror;

    public WebClient(String name, long interval, String url)
    {
        this.name = name;
        this.interval = interval;
        this.url = url;
        this.starttime = 0;
        this.disallowed = 0;
        this.allowed = 0;
        this.total = 0;
        this.http200ok = 0;
        this.httperror = 0;

    }

    private void connecturl()
    {
        BufferedReader in = null;
        try
        {
            URL webquery = new URL(url);
            total++;

            HttpsURLConnection con = (HttpsURLConnection) webquery.openConnection();
            if (con.getResponseCode() == 200)
            {
                http200ok++;
            }
            else
            {
                httperror++;
            }

            in = new BufferedReader(new InputStreamReader(con.getInputStream()));

            String line = null;
            while ((line = in.readLine()) != null)
            {
                if (line.contains("Allowed"))
                {
                    allowed++;
                }
                else if (line.contains("Disallowed"))
                {
                    disallowed++;
                }
            }

        }
        catch (IOException e)
        {
            System.err.println(e);

        }
        finally
        {
            if (in != null)
            {
                try
                {
                    in.close();
                }
                catch (IOException e)
                {// Ignore the exception
                }

            }
        }

    }

    @Override
    public void run()
    {
        boolean end = false;

        if (starttime == 0)
            starttime = System.currentTimeMillis();

        long currenttime = starttime;

        while (!end)
        {
            connecturl();

            currenttime = System.currentTimeMillis();

            if (currenttime - starttime > interval)
            {
                end = true;
            }

            try
            {
                Thread.sleep(1000); // sleep for 1s
            }
            catch (InterruptedException e)
            {
                System.err.println(e);
            }

        }

        System.out.println("Thread: " + name + " , total: " + total + " , httpok: " + http200ok + " , httperror: "
                + httperror + " , allowed: " + allowed + " , disallowed: " + disallowed + " , starttime: " + starttime
                + " , endtime: " + currenttime + " , elapsed time: " + (currenttime - starttime));

    }

    public int getAllowed()
    {
        return allowed;
    }

    public static void main(String[] args)
    {
        long interval = 10 * 60 * 1000;// 10 minutes
        int threadmax = 10;
        int totalallowed = 0;
        String urls_array[] = { "https://www.nighthour.sg/csp-violation-report-endpoint/throttle-demo.php",
                "https://www.nighthour.sg/csp-violation-report-endpoint/throttle-demo.php?ip=192.168.230.76" };

        WebClient threads[] = new WebClient[threadmax];

        for (int i = 0; i < threadmax; i++)
        {
            String tname = "t" + i;
            int urlindex = i % urls_array.length;
            String url = urls_array[urlindex];
            WebClient cthread = new WebClient(tname, interval, url);
            cthread.start();
            threads[i] = cthread;
        }

        for (int i = 0; i < threadmax; i++)
        {
            try
            {
                threads[i].join();
            }
            catch (InterruptedException e)
            {
                System.err.println(e);
            }

            totalallowed += threads[i].getAllowed();

        }

        System.out.println("Total allowd for all threads is " + totalallowed);

    }

}

