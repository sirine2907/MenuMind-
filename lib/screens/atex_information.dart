import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

class ATEXInformationScreen extends StatefulWidget {
  const ATEXInformationScreen({Key? key}) : super(key: key);

  @override
  _ATEXInformationScreenState createState() => _ATEXInformationScreenState();
}

class _ATEXInformationScreenState extends State<ATEXInformationScreen> {
  int _selectedIndex = 1; // Index for 'ATEX Information' in BottomNavigationBar

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: Color(0xFFFFC850),
        appBar: AppBar(
          backgroundColor: Color(0xFFEB874A),
          title: const Text(
            "ATEX Information",
            style: TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.bold,
              fontSize: 18,
            ),
          ),
        ),
        body: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              _buildHeader(),
              const SizedBox(height: 20),
              _buildSection(
                title: "ATEX Standards",
                content: 'ATEX standards refer to two European Directives controlling explosive atmospheres:\n\n'
                    '1. **Directive 99/92/EC (ATEX 137)** - Protection of workers at risk from explosive atmospheres.\n'
                    '2. **Directive 2014/34/EU (ATEX 95)** - Equipment and protective systems intended for use in potentially explosive atmospheres.',
              ),
              const SizedBox(height: 20),
              _buildSection(
                title: "Types of Protection",
                content: 'Types of protection are methods by which equipment can be made safe for use in explosive atmospheres:\n\n'
                    '1. **Intrinsic Safety (Ex i)** - Equipment designed to be incapable of causing an explosion.\n'
                    '2. **Flameproof (Ex d)** - Equipment designed to contain an explosion and prevent flame propagation.\n'
                    '3. **Increased Safety (Ex e)** - Equipment with additional measures to prevent ignition.\n'
                    '4. **Encapsulation (Ex m)** - Equipment encapsulated in a resin to prevent ignition.\n'
                    '5. **Pressurization (Ex p)** - Equipment housed in an enclosure that is pressurized to prevent ingress of explosive gases.',
              ),
              const SizedBox(height: 20),
              _buildSection(
                title: "Equipment Categories",
                content: 'Equipment categories define the level of protection provided by the equipment:\n\n'
                    '1. **Category 1** - Very high level of protection (Zone 0, Zone 20)\n'
                    '2. **Category 2** - High level of protection (Zone 1, Zone 21)\n'
                    '3. **Category 3** - Normal level of protection (Zone 2, Zone 22)',
              ),
            ],
          ),
        ),
        bottomNavigationBar: BottomNavigationBar(
          type: BottomNavigationBarType.fixed,
          selectedItemColor: Color(0xFFEB874A),
          backgroundColor: Colors.white,
          elevation: 0,
          items: [
            BottomNavigationBarItem(
              activeIcon: Icon(Icons.home, size: 28),
              icon: Icon(Icons.home_outlined, size: 28),
              label: "Home",
            ),
            BottomNavigationBarItem(
              activeIcon: Icon(Icons.info, size: 28),
              icon: Icon(Icons.info_outline, size: 28),
              label: "ATEX Info",
            ),
            BottomNavigationBarItem(
              activeIcon: Icon(Icons.favorite, size: 28),
              icon: Icon(Icons.favorite_outline, size: 28),
              label: "Favorites",
            ),
            BottomNavigationBarItem(
              activeIcon: Icon(Icons.person, size: 28),
              icon: Icon(Icons.person_outline, size: 28),
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
          "ATEX Information",
          style: TextStyle(
            fontSize: 28,
            fontWeight: FontWeight.bold,
            color: Colors.white,
          ),
        ),
        SizedBox(height: 10),
        Text(
          "Comprehensive details on ATEX standards, protection types, and equipment categories.",
          style: TextStyle(
            fontSize: 16,
            color: Colors.white70,
          ),
        ),
      ],
    );
  }

  Widget _buildSection({required String title, required String content}) {
    return Container(
      padding: EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Color(0xFFFFE4B5),
        boxShadow: [
          BoxShadow(
            color: Colors.black26,
            spreadRadius: 1,
            blurRadius: 5,
          ),
        ],
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            title,
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.bold,
              color: Color(0xFFEB874A),
            ),
          ),
          SizedBox(height: 10),
          Text(
            content,
            style: TextStyle(
              fontSize: 16,
              color: Colors.black87,
            ),
          ),
        ],
      ),
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
