import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:autrex/constants/color.dart';
import 'package:autrex/constants/icons.dart';
import 'package:autrex/constants/size.dart';
import 'package:autrex/screens/contact_us.dart';

class Pscreen extends StatefulWidget {
  const Pscreen({Key? key}) : super(key: key);

  @override
  _PscreenState createState() => _PscreenState();
}

class _PscreenState extends State<Pscreen> {
  int _selectedIndex = 2;

  void _onItemTapped(int index) {
    setState(() {
      _selectedIndex = index;
    });
    // Add navigation logic here if needed
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: Colors.white,
        body: Stack(
          children: [
            Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    kPrimaryColor.withOpacity(0.8),
                    kPrimaryLight.withOpacity(0.8),
                  ],
                ),
              ),
            ),
            SafeArea(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsets.all(20.0),
                    child: Text(
                      'Explore ATEX Information',
                      style: TextStyle(
                        fontSize: 28,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                  ),
                  Expanded(
                    child: ListView.separated(
                      padding: const EdgeInsets.symmetric(horizontal: 20.0),
                      separatorBuilder: (context, index) => SizedBox(height: 12),
                      itemCount: 9,
                      itemBuilder: (context, index) {
                        return _buildListItem(context, index);
                      },
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        bottomNavigationBar: BottomNavigationBar(
          type: BottomNavigationBarType.fixed,
          selectedItemColor: kPrimaryColor,
          backgroundColor: Colors.transparent,
          elevation: 0,
          items: [
            BottomNavigationBarItem(
              icon: Image.asset(
                ic1,
                height: kBottomNavigationBarItemSize,
              ),
              label: "Home",
            ),
            BottomNavigationBarItem(
              icon: Image.asset(
                ic2,
                height: kBottomNavigationBarItemSize,
              ),
              label: "Contact Us",
            ),
            BottomNavigationBarItem(
              icon: Image.asset(
                ic3,
                height: kBottomNavigationBarItemSize,
              ),
              label: "Favorites",
            ),
            BottomNavigationBarItem(
              icon: Image.asset(
                ic4,
                height: kBottomNavigationBarItemSize,
              ),
              label: "ATEX Info",
            ),
          ],
          currentIndex: _selectedIndex,
          onTap: _onItemTapped,
        ),
      ),
    );
  }

  Widget _buildListItem(BuildContext context, int index) {
    final List<String> itemNames = [
      'TYPE OF PROTECTION FOR ELECTRICAL EQUIPMENT',
      'GAS TEMPERATURE CLASSES',
      'ATEX MARKING',
      'ZONES/EQUIPMENT CATEGORIES',
      'SOME EUROPEAN NOTIFIED BODIES ACCORDING TO 34/2014 / EU',
      'GAS GROUPS',
      'DUST GROUPS',
      'DUST IGNITION TEMPERATURES',
      'Equipment category and equipment protection level (EPL)',
    ];

    final List<IconData> icons = [
      Icons.shield_outlined,
      Icons.thermostat,
      Icons.local_offer_outlined,
      Icons.grid_view,
      Icons.business_outlined,
      Icons.bubble_chart,
      Icons.filter_drama,
      Icons.whatshot_outlined,
      Icons.security_outlined,
    ];

    return GestureDetector(
      onTap: () => _showDialog(context, itemNames[index]),
      child: Card(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(15),
        ),
        elevation: 8,
        child: ListTile(
          contentPadding: const EdgeInsets.all(16.0),
          leading: Icon(
            icons[index],
            color: kPrimaryColor,
            size: 40,
          ),
          title: Text(
            itemNames[index],
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.bold,
              color: kPrimaryColor,
            ),
          ),
        ),
      ),
    );
  }

  void _showDialog(BuildContext context, String title) {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: Text("What do you want to know?"),
          content: Text(
            "You selected: $title. Would you like to know more about this topic?",
            style: TextStyle(fontSize: 16),
          ),
          actions: <Widget>[
            TextButton(
              child: Text("Yes"),
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (context) => Contact()),
                );
              },
            ),
            TextButton(
              child: Text("No"),
              onPressed: () {
                Navigator.of(context).pop();
              },
            ),
          ],
        );
      },
    );
  }
}
