import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:autrex/constants/icons.dart';
import 'package:autrex/constants/size.dart';

class AboutAutrisScreen extends StatefulWidget {
  const AboutAutrisScreen({Key? key}) : super(key: key);

  @override
  _AboutAutrisScreenState createState() => _AboutAutrisScreenState();
}

class _AboutAutrisScreenState extends State<AboutAutrisScreen> {
  int _selectedIndex = 3; // Index for 'About Us' in BottomNavigationBar

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: Color(0xFFFFC850), // Background color adjustment
        appBar: AppBar(
          backgroundColor: Color(0xFFEB874A), // App bar color adjustment
          title: Text(
            "About Autris",
            style: TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.bold,
              fontSize: 18,
            ),
          ),
        ),
        body: SingleChildScrollView(
          padding: EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              _buildHeader(),
              SizedBox(height: 20),
              _buildSectionTitle("Our Vision"),
              _buildSectionContent(
                "To pioneer the future of automation and intelligent systems, transforming industries through cutting-edge technology and innovative solutions.",
              ),
              SizedBox(height: 20),
              _buildSectionTitle("Our Mission"),
              _buildSectionContent(
                "To empower businesses with advanced automation technologies, enhancing operational efficiency, productivity, and competitiveness.",
              ),
              SizedBox(height: 20),
              _buildSectionTitle("Core Values"),
              _buildCoreValues(),
              SizedBox(height: 20),
              _buildSectionTitle("Expertise Areas"),
              _buildExpertiseAreas(),
            ],
          ),
        ),
        bottomNavigationBar: BottomNavigationBar(
          type: BottomNavigationBarType.fixed,
          selectedItemColor: Color(0xffEB874A), // Selected item color adjustment
          backgroundColor: Colors.white,
          elevation: 0,
          items: [
            BottomNavigationBarItem(
              activeIcon: Image.asset(
                ic11,
                height: kBottomNavigationBarItemSize,
              ),
              icon: Image.asset(
                ic1,
                height: kBottomNavigationBarItemSize,
              ),
              label: "Home",
            ),
            BottomNavigationBarItem(
              activeIcon: Image.asset(
                ic22,
                height: kBottomNavigationBarItemSize,
              ),
              icon: Image.asset(
                ic2,
                height: kBottomNavigationBarItemSize,
              ),
              label: "Contact Us",
            ),
            BottomNavigationBarItem(
              activeIcon: Image.asset(
                ic33,
                height: kBottomNavigationBarItemSize,
              ),
              icon: Image.asset(
                ic3,
                height: kBottomNavigationBarItemSize,
              ),
              label: "Favorites",
            ),
            BottomNavigationBarItem(
              activeIcon: Image.asset(
                ic44,
                height: kBottomNavigationBarItemSize,
              ),
              icon: Image.asset(
                ic4,
                height: kBottomNavigationBarItemSize,
              ),
              label: "About Us",
            ),
          ],
          currentIndex: _selectedIndex,
          onTap: _onItemTapped,
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          "Welcome to Autris",
          style: TextStyle(
            fontSize: 28,
            fontWeight: FontWeight.bold,
          ),
        ),
        SizedBox(height: 10),
        Text(
          "Automation and Intelligent Systems",
          style: TextStyle(
            fontSize: 24,
            fontWeight: FontWeight.bold,
            color: Color(0xFFEB874A), // Accent color adjustment
          ),
        ),
        SizedBox(height: 20),
        Text(
          "We are a cutting-edge technology company specializing in designing, developing, and implementing advanced automation and intelligent systems solutions for businesses across various industries.",
          style: TextStyle(
            fontSize: 16,
            color: Colors.black54,
          ),
        ),
      ],
    );
  }

  Widget _buildSectionTitle(String title) {
    return Text(
      title,
      style: TextStyle(
        fontSize: 20,
        fontWeight: FontWeight.bold,
      ),
    );
  }

  Widget _buildSectionContent(String content) {
    return Padding(
      padding: EdgeInsets.symmetric(vertical: 10),
      child: Text(
        content,
        style: TextStyle(
          fontSize: 16,
          color: Colors.black54,
        ),
      ),
    );
  }

  Widget _buildCoreValues() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        _buildCoreValueItem(
          "Innovation",
          "Constantly innovating to deliver cutting-edge solutions.",
        ),
        _buildCoreValueItem(
          "Quality",
          "Ensuring highest standards of quality in every solution.",
        ),
        _buildCoreValueItem(
          "Customer Satisfaction",
          "Commitment to exceeding customer expectations.",
        ),
      ],
    );
  }

  Widget _buildCoreValueItem(String title, String description) {
    return Padding(
      padding: EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(
            Icons.check_circle,
            color: Color(0xFFEB874A),
          ),
          SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  title,
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                  ),
                ),
                SizedBox(height: 4),
                Text(
                  description,
                  style: TextStyle(
                    fontSize: 14,
                    color: Colors.black54,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildExpertiseAreas() {
    return Wrap(
      spacing: 16,
      runSpacing: 12,
      children: <Widget>[
        _buildExpertiseAreaItem("Industrial Automation"),
        _buildExpertiseAreaItem("Robotics"),
        _buildExpertiseAreaItem("AI and Machine Learning"),
        _buildExpertiseAreaItem("Computer Vision"),
        _buildExpertiseAreaItem("IoT Solutions"),
      ],
    );
  }

  Widget _buildExpertiseAreaItem(String title) {
    return Chip(
      label: Text(
        title,
        style: TextStyle(
          color: Colors.white,
        ),
      ),
      backgroundColor: Color(0xFFEB874A),
    );
  }

  void _onItemTapped(int index) {
    setState(() {
      _selectedIndex = index;
      // Handle navigation or other state changes as needed
      // For example, navigate to different screens based on index
    });
  }
}
